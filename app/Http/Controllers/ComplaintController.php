<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\OffenceType;
use App\Models\User;
use App\Models\Verification;
use App\Http\Requests\StoreComplaintRequest;
use App\Http\Requests\UpdateComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Notifications\VerificationAssignedNotification;
use App\Services\ComplainantNotifyService;
use App\Services\PrintService;
use App\Services\SmsService;
use App\Services\SmsTemplates;
use App\Services\TrackingNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\SecureFileService;

class ComplaintController extends Controller
{
    use \App\Http\Controllers\Concerns\EnforcesStageLock;
    use \App\Http\Controllers\Concerns\PreventsLostUpdates;

    /**
     * Allowed upload extensions for complaint files. Never trust the
     * client-supplied extension; derive a safe one from the detected MIME.
     */
    private const SAFE_UPLOAD_EXT = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    /**
     * Pick a safe filename extension from the file's detected (server-side) type.
     * Falls back to rejecting anything outside the whitelist so a .php/.phtml
     * payload can never be written with an executable extension.
     */
    protected function safeExtension(\Illuminate\Http\UploadedFile $file): ?string
    {
        $ext = strtolower((string) $file->guessExtension()); // from MIME, not client input
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        return in_array($ext, self::SAFE_UPLOAD_EXT, true) ? $ext : null;
    }

    /**
     * Move an uploaded complaint file into public/uploads/complaints.
     */
    protected function uploadComplaintFile(Request $request, string $field, ?string $existing = null): ?string
    {
        if (!$request->hasFile($field)) {
            return $existing;
        }

        $files = $request->file($field);
        $dir = public_path('uploads/complaints');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (is_array($files)) {
            $paths = [];
            if ($existing) {
                $decoded = json_decode($existing, true);
                if (is_array($decoded)) {
                    $paths = $decoded;
                } else {
                    $paths[] = $existing;
                }
            }
            foreach ($files as $file) {
                if ($file) {
                    $ext = $this->safeExtension($file);
                    if ($ext === null) { continue; } // reject disallowed/executable types
                    $name = Str::random(24) . '.' . $ext;
                    $file->move($dir, $name);
                    $paths[] = 'uploads/complaints/' . $name;
                }
            }
            return count($paths) > 0 ? json_encode($paths) : null;
        }

        // Single file
        $file = $files;
        $ext = $this->safeExtension($file);
        if ($ext === null) {
            return $existing; // reject disallowed/executable types
        }
        $name = Str::random(24) . '.' . $ext;
        $file->move($dir, $name);

        if ($existing && !json_decode($existing) && is_file(public_path($existing))) {
            @unlink(public_path($existing));
        }

        return 'uploads/complaints/' . $name;
    }


    /** @deprecated use uploadComplaintFile */
    protected function uploadAttachment(Request $request, ?Complaint $complaint = null): ?string
    {
        return $this->uploadComplaintFile($request, 'attachment', $complaint?->attachment);
    }

    /**
     * Attach per-accused identity files uploaded as accused_cnic_front[i],
     * accused_cnic_back[i], accused_picture[i], accused_passport[i].
     */
    protected function applyAccusedIdentityFiles(Request $request, ?array $accusedData): ?array
    {
        if (!$accusedData || !is_array($accusedData)) {
            return $accusedData;
        }

        $fields = [
            'cnic_front'           => 'accused_cnic_front',
            'cnic_back'            => 'accused_cnic_back',
            'picture'              => 'accused_picture',
            'passport_attachment'  => 'accused_passport',
        ];

        $dir = public_path('uploads/complaints/accused');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $accusedData, 'accused');
        foreach ($accusedData as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($fields as $attr => $input) {
                $files = $request->file($input, []);
                $file = is_array($files) ? ($files[$index] ?? null) : null;
                if ($file) {
                    $ext = $this->safeExtension($file);
                    if ($ext === null) { continue; } // reject disallowed/executable types
                    $name = Str::random(24) . '.' . $ext;
                    $file->move($dir, $name);
                    if (!empty($row[$attr]) && is_string($row[$attr]) && is_file(public_path($row[$attr]))) {
                        @unlink(public_path($row[$attr]));
                    }
                    $accusedData[$index][$attr] = 'uploads/complaints/accused/' . $name;
                } elseif (!empty($row[$attr]) && !is_string($row[$attr])) {
                    unset($accusedData[$index][$attr]);
                }
            }
        }

        return $accusedData;
    }

    public function index()
    {
        $this->authorize('viewAny', Complaint::class);

        $perPage = min(50, max(10, (int) request('per_page', 15)));

        $query = Complaint::visibleTo(request()->user())
            ->with(['enquiry', 'verification.officer', 'caseFiles', 'circle']);

        if ($search = trim((string) request('search'))) {
            $query->where(function ($qq) use ($search) {
                $qq->where('tracking_no', 'like', "{$search}%")
                    ->orWhere('diary_no', 'like', "{$search}%")
                    ->orWhere('cnic', 'like', "{$search}%")
                    ->orWhere('complainant_name', 'like', "{$search}%") // Optimized for B-Tree index
                    ->orWhere('id', $search);
            });
        }

        $complaints = $query->latest('id')->simplePaginate($perPage)->withQueryString();

        if (request()->expectsJson()) {
            return ComplaintResource::collection($complaints);
        }

        return view('pages.all-complaints', compact('complaints'));
    }

    /**
     * Lightweight typeahead for forms (never load full complaint tables).
     */
    public function search(Request $request)
    {
        $this->authorize('viewAny', Complaint::class);

        $q = trim((string) $request->get('q', ''));
        $query = Complaint::visibleTo($request->user())
            ->with('verification:id,complaint_id,verification_officer_id,status,assigned_at')
            ->whereNotNull('tracking_no');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('tracking_no', 'like', $q . '%')
                    ->orWhere('diary_no', 'like', $q . '%')
                    ->orWhere('cnic', 'like', $q . '%')
                    ->orWhere('complainant_name', 'like', $q . '%');
            });
        }

        $rows = $query->latest('id')->limit(20)->get([
            'id', 'tracking_no', 'diary_no', 'complainant_name', 'father_name', 'cnic', 'contact_no',
            'contact_country_code', 'profession', 'offence_type', 'cmu', 'description', 'entry_time',
            'created_at', 'status', 'gender', 'email', 'whatsapp_no', 'nationality', 'passport_no',
            'address', 'post_address', 'district', 'priority_type', 'source', 'amount_involved',
            'crime_mediums', 'bank_name_sender', 'bank_name_receiver', 'account_no_sender',
            'account_no_receiver', 'transaction_date', 'occurrence_date', 'laws', 'platforms',
            'platform_profile_page', 'platform_username', 'platform_email_involved',
            'platform_mobile_involved', 'evidence', 'initial_accused', 'attachment', 'cnic_front',
            'cnic_back', 'passport_attachment', 'picture', 'report_date', 'reporting_time',
        ]);

        $rows = $rows->map(function ($c) {
            $fileAttrs = ['attachment', 'cnic_front', 'cnic_back', 'passport_attachment', 'picture'];
            foreach ($fileAttrs as $attr) {
                if (!empty($c->{$attr}) && is_string($c->{$attr}) && !str_starts_with($c->{$attr}, 'http')) {
                    $c->{$attr . '_url'} = SecureFileService::url($c->{$attr}, $c);
                }
            }
            $accused = $c->initial_accused;
            if (is_array($accused)) {
                foreach ($accused as &$a) {
                    if (!is_array($a)) {
                        continue;
                    }
                    foreach (['cnic_front', 'cnic_back', 'picture', 'passport_attachment'] as $attr) {
                        if (!empty($a[$attr]) && is_string($a[$attr]) && !str_starts_with($a[$attr], 'http')) {
                            $a[$attr . '_url'] = SecureFileService::url($a[$attr], $c);
                        }
                    }
                }
                unset($a);
                $c->initial_accused = $accused;
            }
            return $c;
        });

        return response()->json(['data' => $rows]);
    }

    public function bulkAction(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['admin', 'circle_incharge']), 403);

        $data = $request->validate([
            'ids'                 => 'required|array|min:1',
            'ids.*'               => 'integer|exists:complaints,id',
            'action'              => 'required|string|in:closure,merge,transfer',
            'closure_reason'      => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence',
            'merge_complaint_id'  => 'nullable|integer|exists:complaints,id',
            'transfer_department' => 'nullable|string|max:255',
            'transfer_circle_id'  => 'nullable|integer|exists:circles,id',
        ]);

        $complaints = Complaint::visibleTo($user)->whereIn('id', $data['ids'])->get();

        if ($complaints->isEmpty()) {
            return response()->json(['message' => 'No complaints found for the selected records.'], 404);
        }

        DB::transaction(function () use ($complaints, $data, $user) {
            foreach ($complaints as $complaint) {
                switch ($data['action']) {
                    case 'closure':
                        $complaint->update([
                            'status'         => 'closed',
                            'final_status'   => 'closed',
                            'closure_reason' => $data['closure_reason'] ?? null,
                        ]);
                        $complaint->verification?->update([
                            'status'         => 'closed',
                            'completed_at'   => now(),
                            'recommendation' => 'closure',
                            'closure_reason' => $data['closure_reason'] ?? null,
                        ]);
                        break;

                    case 'merge':
                        $complaint->update([
                            'status'         => 'merged',
                            'final_status'   => 'merged',
                            'merged_with_id' => $data['merge_complaint_id'] ?? null,
                        ]);
                        $complaint->verification?->update([
                            'status'             => 'approved',
                            'approved_at'        => now(),
                            'recommendation'     => 'merge',
                            'merge_complaint_id' => $data['merge_complaint_id'] ?? null,
                        ]);
                        break;

                    case 'transfer':
                        $complaint->update([
                            'status'                 => 'transferred',
                            'final_status'           => 'transferred',
                            'transfer_to_department' => $data['transfer_department'] ?? null,
                            'transfer_to_circle_id'  => $data['transfer_circle_id'] ?? null,
                        ]);
                        $complaint->verification?->update([
                            'status'              => 'approved',
                            'approved_at'         => now(),
                            'recommendation'      => 'transfer',
                            'transfer_department' => $data['transfer_department'] ?? null,
                            'transfer_circle_id'  => $data['transfer_circle_id'] ?? null,
                        ]);
                        break;
                }
            }
        });

        return response()->json(['message' => count($complaints) . ' complaint(s) updated.']);
    }

public function create()
    {
        $offenceTypes = OffenceType::orderBy('group')->orderBy('name')->get();
        return view('pages.newcomplaint', compact('offenceTypes'));
    }

    public function store(StoreComplaintRequest $request)
    {
        try {
            return $this->storeComplaint($request);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Unknown column') || str_contains($e->getMessage(), 'no such column')) {
                return response()->json([
                    'message' => 'Database columns missing. Server pe chalao: php artisan migrate --force',
                ], 500);
            }
            throw $e;
        }
    }

    private function storeComplaint(StoreComplaintRequest $request)
    {
        try {
            $data = $request->validated();
            $officerId = $data['verification_officer_id'] ?? null;
            $assignPriority = $data['assign_priority_type'] ?? ($data['priority_type'] ?? 'normal');
            unset($data['verification_officer_id'], $data['assign_priority_type']);

            $data['laws'] = $request->has('laws') ? $request->laws : null;
            $data['evidence'] = $request->has('evidence') ? $request->evidence : null;
            $data['platforms'] = $request->has('platforms') ? $request->platforms : null;
            $data['crime_mediums'] = $request->has('crime_mediums') ? $request->crime_mediums : null;
            if ($request->has('initial_accused')) {
                $accused = $request->input('initial_accused');
                $data['initial_accused'] = is_string($accused) ? (json_decode($accused, true) ?: []) : $accused;
            }
            $data['initial_accused'] = $this->applyAccusedIdentityFiles($request, $data['initial_accused'] ?? null);
            $data['user_id'] = Auth::id();
            $data['operator_id'] = $data['operator_id'] ?? Auth::id();
            $data['operator_name'] = $data['operator_name'] ?? Auth::user()?->name ?? 'System';
            $data['operator_designation'] = $data['operator_designation'] ?? Auth::user()?->designation ?? 'Operator';
            $data['diary_no'] = $data['diary_no'] ?? '';
            $data['source'] = $data['source'] ?? 'Walk-in';
            $user = Auth::user();
            if ($user && $user->circle_id && !$user->seesAllData()) {
                $data['circle_id'] = $user->circle_id;
            } elseif (empty($data['circle_id']) && $user?->circle_id) {
                $data['circle_id'] = $user->circle_id;
            }
            $data['attachment'] = $this->uploadComplaintFile($request, 'attachment');
            $data['cnic_front'] = $this->uploadComplaintFile($request, 'cnic_front');
            $data['cnic_back'] = $this->uploadComplaintFile($request, 'cnic_back');
            $data['passport_attachment'] = $this->uploadComplaintFile($request, 'passport_attachment');
            $data['picture'] = $this->uploadComplaintFile($request, 'picture');

            $scrutinyResult = $data['scrutiny_result'] ?? null;
            if ($scrutinyResult === 'complete') {
                $data['status'] = 'complete';

                $circle = isset($data['circle_id']) ? \App\Models\Circle::find($data['circle_id']) : null;
                $generator = app(\App\Services\TrackingNumberGenerator::class);

                $maxAttempts = 10;
                for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                    $data['tracking_no'] = $generator->generate($circle);
                    try {
                        $complaint = DB::transaction(function () use ($data) {
                            return Complaint::create($data);
                        });
                        break;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($attempt >= $maxAttempts - 1 || !str_contains($e->getMessage(), 'complaints_tracking_no_unique')) {
                            throw $e;
                        }
                    }
                }
            } else {
                $data['status'] = 'incomplete';
                $complaint = Complaint::create($data);
            }

            if ($scrutinyResult === 'complete' && $officerId) {
                $targetVo = User::find((int) $officerId);
                if (!$targetVo || ((int) $targetVo->circle_id !== (int) $complaint->circle_id && !$user?->seesAllData())) {
                    return response()->json([
                        'message' => 'Verification Officer must belong to the same circle as the complaint (' . ($complaint->circle?->name ?? 'station') . ').',
                    ], 422);
                }
                $this->assignVerificationOfficer($complaint, (int) $officerId, $assignPriority);
            }

            // When complaint gets a tracking number, notify complainant immediately (WhatsApp deep-link).
            $notify = null;
            if ($complaint->tracking_no) {
                $notify = app(ComplainantNotifyService::class)->notifyRegistration($complaint);
                $complaint->refresh();
                $this->sendComplainantSms(
                    $complaint,
                    SmsTemplates::complaintRegistered($complaint, 'en'),
                    SmsTemplates::complaintRegistered($complaint, 'ur'),
                    'complaint_registered'
                );
            }

            return response()->json([
                'message' => 'Complaint successfully registered',
                'data' => new ComplaintResource($complaint),
                'complainant_notify' => $notify,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'DEBUG ERROR: ' . $e->getMessage() . ' at Line: ' . $e->getLine() . ' File: ' . $e->getFile()
            ], 500);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Complaint registered successfully — ' . ($complaint->tracking_no ?? 'N/A'))
            ->with('complainant_whatsapp_url', $notify['whatsapp_url'] ?? null);
    }

    public function show(Complaint $complaint)
    {
        abort_unless(
            Complaint::visibleTo(request()->user())->whereKey($complaint->id)->exists(),
            404
        );
        $this->authorize('view', $complaint);

        return new ComplaintResource($complaint->load(['enquiry', 'verification', 'caseFiles', 'circle']));
    }

    public function edit(Complaint $complaint)
    {
        $offenceTypes = OffenceType::orderBy('group')->orderBy('name')->get();
        return view('pages.edit-complaint', compact('complaint', 'offenceTypes'));
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint)
    {
        abort_unless(
            Complaint::visibleTo($request->user())->whereKey($complaint->id)->exists(),
            404
        );
        if ($stale = $this->denyIfStale($complaint, $request)) {
            return $stale;
        }

        $data = $request->validated();
        $officerId = $data['verification_officer_id'] ?? null;
        $assignPriority = $data['assign_priority_type'] ?? ($data['priority_type'] ?? 'normal');
        unset($data['verification_officer_id'], $data['assign_priority_type']);

        $data['laws']     = $request->has('laws') ? $request->laws : $complaint->laws;
        $data['evidence'] = $request->has('evidence') ? $request->evidence : $complaint->evidence;
        $data['platforms'] = $request->has('platforms') ? $request->platforms : $complaint->platforms;
        $data['crime_mediums'] = $request->has('crime_mediums') ? $request->crime_mediums : $complaint->crime_mediums;
        if ($request->has('initial_accused')) {
            $accused = $request->input('initial_accused');
            $data['initial_accused'] = is_string($accused) ? (json_decode($accused, true) ?: []) : $accused;
        }
        $data['initial_accused'] = $this->applyAccusedIdentityFiles($request, $data['initial_accused'] ?? null);
        foreach (['attachment', 'cnic_front', 'cnic_back', 'passport_attachment', 'picture'] as $fileField) {
            if ($request->hasFile($fileField)) {
                $data[$fileField] = $this->uploadComplaintFile($request, $fileField, $complaint->{$fileField});
            } else {
                unset($data[$fileField]);
            }
        }

        $scrutinyResult = $data['scrutiny_result'] ?? $complaint->scrutiny_result;

        $hadTracking = (bool) $complaint->tracking_no;

        if ($scrutinyResult === 'complete' && !$complaint->tracking_no) {
            $circle = $complaint->circle;
            $generator = app(\App\Services\TrackingNumberGenerator::class);
            $maxAttempts = 10;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $data['tracking_no'] = $generator->generate($circle);
                $data['status'] = 'complete';
                try {
                    $complaint->update($data);
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($attempt >= $maxAttempts - 1 || !str_contains($e->getMessage(), 'complaints_tracking_no_unique')) {
                        throw $e;
                    }
                }
            }
        } else {
            $data['status'] = $scrutinyResult === 'complete' ? 'complete' : 'incomplete';
            $complaint->update($data);
        }

        $complaint->refresh();
        if ($scrutinyResult === 'complete' && $officerId && !$complaint->verification) {
            $this->assignVerificationOfficer($complaint, (int) $officerId, $assignPriority);
        }

        $notify = null;
        $complaint->refresh();
        if ($complaint->tracking_no && (!$hadTracking || !$complaint->registration_notified_at)) {
            $notify = app(ComplainantNotifyService::class)->notifyRegistration($complaint);
            $complaint->refresh();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Complaint updated successfully',
                'data'    => new ComplaintResource($complaint->load('verification')),
                'complainant_notify' => $notify,
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', 'Complaint #' . $complaint->tracking_no . ' updated successfully');
    }

    /**
     * Generate & return the printable 80mm complaint slip HTML.
     */
    public function slip(Complaint $complaint, PrintService $print)
    {
        abort_unless(
            Complaint::visibleTo(request()->user())->whereKey($complaint->id)->exists(),
            404
        );

        if (!$complaint->slip_generated) {
            $complaint->update([
                'slip_generated'    => true,
                'slip_generated_at' => now(),
                'slip_number'       => $complaint->slip_number ?: ($complaint->tracking_no ?: ('SLP-' . $complaint->id)),
            ]);
        }

        try {
            return response()->json([
                'html' => $print->slipPrintDocument($complaint),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not generate slip: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Generate & return the full A4 complaint report (complaint + accused).
     */
    public function report(Complaint $complaint, PrintService $print)
    {
        abort_unless(
            Complaint::visibleTo(request()->user())->whereKey($complaint->id)->exists(),
            404
        );

        try {
            return response()->json([
                'html' => $print->complaintReportPrintDocument($complaint),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not generate report: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Public complaint receipt verification (scanned via QR on slip).
     */
    public function verifySlip(int $id, string $token)
    {
        $complaint = Complaint::with('circle')->findOrFail($id);
        $expected = substr(hash_hmac('sha256', 'complaint:' . $complaint->id, (string) config('app.key')), 0, 32);

        abort_unless(hash_equals($expected, $token), 404);

        return view('complaints.slip-verify', compact('complaint'));
    }

    public function destroy(Complaint $complaint)
    {
        $this->authorize('delete', $complaint);

        activity()->useLog('complaints')
            ->performedOn($complaint)
            ->causedBy(auth()->user())
            ->withProperties(['tracking_no' => $complaint->tracking_no])
            ->log('Complaint deleted: ' . ($complaint->tracking_no ?? $complaint->id));

        $complaint->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Complaint deleted successfully']);
        }

        return redirect()->back()
            ->with('success', 'Complaint deleted successfully');
    }

    public function scrutiny(Request $request, Complaint $complaint, TrackingNumberGenerator $trackingGen)
    {
        abort_unless(
            Complaint::visibleTo($request->user())->whereKey($complaint->id)->exists(),
            403,
            'Unauthorized. You cannot perform scrutiny on complaints outside your circle.'
        );

        $request->validate([
            'status' => 'required|string|in:complete,incomplete,invalid,irrelevant',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $complaint->status = $request->status;
        $complaint->scrutiny_result = $request->status;

        if ($request->status === 'complete' && !$complaint->tracking_no) {
            $circle = $complaint->circle;
            $complaint->tracking_no = $trackingGen->generate($circle);
        }

        if ($request->has('remarks')) {
            $complaint->operator_remarks = $request->remarks;
        }

        $complaint->save();

        $notify = null;
        if ($complaint->tracking_no && !$complaint->registration_notified_at) {
            $notify = app(ComplainantNotifyService::class)->notifyRegistration($complaint);
            $complaint->refresh();
        }

        // Send status-update SMS to complainant once a tracking number exists.
        if ($complaint->tracking_no) {
            $this->sendComplainantSms(
                $complaint,
                SmsTemplates::complaintStatusUpdated($complaint, 'en'),
                SmsTemplates::complaintStatusUpdated($complaint, 'ur'),
                'complaint_scrutiny'
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Scrutiny result updated',
                'status' => $complaint->status,
                'tracking_no' => $complaint->tracking_no,
                'complainant_notify' => $notify,
                'data' => new ComplaintResource($complaint->fresh()),
            ]);
        }

        return redirect()->route('all.complaints')
            ->with('success', 'Complaint status updated to ' . $request->status);
    }

    /**
     * Resend / open registration WhatsApp message for a tracked complaint.
     */
    public function notifyComplainant(Complaint $complaint, ComplainantNotifyService $notify)
    {
        $this->authorize('view', $complaint);
        abort_unless($complaint->tracking_no, 422, 'Tracking number not generated yet.');

        $payload = $notify->notifyRegistration($complaint);

        // Resend the registration SMS as well.
        $this->sendComplainantSms(
            $complaint,
            SmsTemplates::complaintRegistered($complaint, 'en'),
            SmsTemplates::complaintRegistered($complaint, 'ur'),
            'complaint_registered'
        );

        return response()->json([
            'message' => 'Registration message ready for complainant',
            'complainant_notify' => $payload,
            'data' => new ComplaintResource($complaint->fresh()),
        ]);
    }

    /**
     * Operator / circle-incharge one-click "direct assign":
     * create a verification and assign a verification officer to this complaint.
     */
    public function directAssign(Request $request, Complaint $complaint)
    {
        $actor = $request->user();
        abort_unless(
            $actor && ($actor->seesAllData() || $actor->canAccessCircle($complaint->circle_id)),
            403,
            'Unauthorized. You cannot assign complaints outside your circle jurisdiction.'
        );

        $data = $request->validate([
            'verification_officer_id' => 'required|integer|exists:users,id',
            'priority_type'           => 'required|in:normal,high,critical',
        ]);

        $officer = User::findOrFail((int) $data['verification_officer_id']);
        if ($complaint->circle_id && (int) $officer->circle_id !== (int) $complaint->circle_id && !$actor->seesAllData()) {
            return response()->json([
                'message' => 'Verification Officer must belong to the same circle as the complaint (' . ($complaint->circle?->name ?? 'station') . ').',
            ], 422);
        }

        $verification = $this->assignVerificationOfficer(
            $complaint,
            (int) $data['verification_officer_id'],
            $data['priority_type']
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Verification assigned to ' . ($verification->officer?->name ?? 'officer'),
                'data'    => $verification->load('officer', 'complaint'),
            ], 201);
        }

        return back()->with('success', 'Verification assigned to ' . ($verification->officer?->name ?? 'officer'));
    }

    /**
     * Send the complainant an SMS (bilingual) and log it against the complaint.
     */
    protected function sendComplainantSms(Complaint $complaint, string $en, string $ur, string $trigger): void
    {
        $phone = preg_replace('/\D+/', '', ($complaint->contact_country_code ?: '+92') . ($complaint->contact_no ?: ''));
        if (!$phone) {
            return;
        }

        $options = [
            'country_code'   => $complaint->contact_country_code ?: '+92',
            'recipient_type' => 'complainant',
            'subject_type'   => 'complaint',
            'subject_id'     => $complaint->id,
            'trigger'        => $trigger,
        ];

        app(SmsService::class)->sendBilingual($phone, $en, $ur, $options);
    }

    protected function assignVerificationOfficer(Complaint $complaint, int $officerId, string $priority = 'normal'): Verification
    {
        $priority = in_array($priority, ['normal', 'high', 'critical'], true) ? $priority : 'normal';
        $officer = User::find($officerId);

        // Keep complaint in VO's circle so Circle Incharge of that circle gets the queue
        if ($officer?->circle_id && (int) $complaint->circle_id !== (int) $officer->circle_id) {
            if (!$complaint->circle_id) {
                $complaint->update(['circle_id' => $officer->circle_id]);
            }
        } elseif (!$complaint->circle_id && Auth::user()?->circle_id) {
            $complaint->update(['circle_id' => Auth::user()->circle_id]);
        }

        $verification = DB::transaction(function () use ($complaint, $officerId, $priority) {
            if ($complaint->verification) {
                return $complaint->verification;
            }

            return Verification::create([
                'complaint_id'            => $complaint->id,
                'verification_officer_id' => $officerId,
                'priority_type'           => $priority,
                'status'                  => 'assigned',
                'assigned_by'             => Auth::id(),
                'assigned_at'             => now(),
            ]);
        });

        $verification->loadMissing('officer');
        try {
            $verification->officer?->notify(new VerificationAssignedNotification($verification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('VerificationAssignedNotification failed: ' . $e->getMessage());
        }

        // SMS the assigned Verification Officer.
        try {
            if ($verification->officer) {
                app(SmsService::class)->sendToUser(
                    $verification->officer,
                    SmsTemplates::verificationAssigned($verification, $verification->officer, 'en'),
                    SmsTemplates::verificationAssigned($verification, $verification->officer, 'ur'),
                    [
                        'subject_type' => 'verification',
                        'subject_id'   => $verification->id,
                        'trigger'      => 'verification_assigned',
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Officer SMS failed: ' . $e->getMessage());
        }

        return $verification;
    }
}
