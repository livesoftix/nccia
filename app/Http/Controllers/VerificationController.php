<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\OffenceType;
use App\Models\User;
use App\Models\Verification;
use App\Models\VerificationReport;
use App\Notifications\VerificationAssignedNotification;
use App\Notifications\VerificationSubmittedNotification;
use App\Notifications\ComplainantMessageNotification;
use App\Services\ComplainantNotifyService;
use App\Services\EnquiryNumberGenerator;
use App\Services\OfficerAssignmentService;
use App\Services\SmsService;
use App\Services\SmsTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerificationController extends Controller
{
    use \App\Http\Controllers\Concerns\EnforcesStageLock;
    use \App\Http\Controllers\Concerns\PreventsLostUpdates;

    private const ACCUSED_FILE_FIELDS = [
        'photo'                => 'accused_photo',
        'cnic_front'           => 'accused_cnic_front',
        'cnic_back'            => 'accused_cnic_back',
        'passport_attachment'  => 'accused_passport',
        'picture'              => 'accused_picture',
        'other_attachment'     => 'accused_other',
    ];

    private function parseAccusedInput(Request $request): ?array
    {
        $accused = $request->input('accused');
        if (is_string($accused)) {
            $decoded = json_decode($accused, true);
            $accused = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }
        if (!is_array($accused)) {
            return null;
        }

        return array_values(array_filter($accused, function ($row) {
            if (!is_array($row)) {
                return false;
            }
            return !empty($row['name']) || !empty($row['cnic']) || !empty($row['father_name']);
        }));
    }

    private function applyAccusedIdentityFiles(Request $request, array $accusedData, string $directory): array
    {
        $rules = [];
        foreach (self::ACCUSED_FILE_FIELDS as $attr => $input) {
            $rules[$input] = 'sometimes|array|max:100';
            $types = in_array($attr, ['photo', 'picture'], true) ? 'jpg,jpeg,png' : 'jpg,jpeg,png,pdf';
            $rules[$input . '.*'] = 'nullable|file|mimes:' . $types . '|max:10240';
        }
        $request->validate($rules);
        \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $accusedData, 'accused');
        foreach ($accusedData as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (self::ACCUSED_FILE_FIELDS as $attr => $input) {
                $files = $request->file($input, []);
                $file = is_array($files) ? ($files[$index] ?? null) : null;
                if ($file) {
                    $accusedData[$index][$attr] = $file->store($directory, 'public');
                } elseif (!empty($row[$attr]) && is_string($row[$attr])) {
                    $accusedData[$index][$attr] = $row[$attr];
                } else {
                    $accusedData[$index][$attr] = $row[$attr] ?? null;
                }
                if (isset($accusedData[$index][$attr]) && !is_string($accusedData[$index][$attr])) {
                    unset($accusedData[$index][$attr]);
                }
            }
        }

        return $accusedData;
    }

    private function accusedWithUrls(?array $accused, \Illuminate\Database\Eloquent\Model $record): ?array
    {
        if (!$accused) {
            return $accused;
        }

        return array_map(function ($a) use ($record) {
            if (!is_array($a)) {
                return $a;
            }
            foreach (array_keys(self::ACCUSED_FILE_FIELDS) as $field) {
                if (!empty($a[$field]) && is_string($a[$field])) {
                    $a[$field . '_url'] = \App\Services\SecureFileService::url($a[$field], $record);
                }
            }
            return $a;
        }, $accused);
    }

    public function index()
    {
        $query = Verification::visibleTo(request()->user())->with(['complaint', 'officer', 'assignedBy']);

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('complaint', function ($cq) use ($search) {
                    // Prefix search uses indexes; avoid leading % on tracking_no
                    $cq->where('tracking_no', 'like', "{$search}%")
                       ->orWhere('complainant_name', 'like', "{$search}%");
                })->orWhere('id', $search);
            });
        }

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        $perPage = min(50, max(10, (int) request('per_page', 15)));
        $verifications = $query->latest('id')->paginate($perPage)->withQueryString();

        if (request()->expectsJson()) {
            return response()->json($verifications);
        }

        $statsQuery = Verification::visibleTo(request()->user());
        $stats = [
            'total'    => (clone $statsQuery)->count(),
            'pending'  => (clone $statsQuery)->where('status', 'pending_assignment')->count(),
            'progress' => (clone $statsQuery)->whereIn('status', ['assigned', 'in_progress', 'sent_back', 'submitted'])->count(),
            'approved' => (clone $statsQuery)->whereIn('status', ['approved', 'closed', 'merged', 'transferred'])->count(),
        ];

        return view('verifications.index', compact('verifications', 'stats'));
    }

    public function stats()
    {
        $user = request()->user();
        $query = Verification::visibleTo($user);

        return response()->json([
            'total'    => (clone $query)->count(),
            'pending'  => (clone $query)->where('status', 'pending_assignment')->count(),
            'progress' => (clone $query)->whereIn('status', ['assigned', 'in_progress', 'sent_back', 'submitted'])->count(),
            'approved' => (clone $query)->whereIn('status', ['approved', 'closed', 'merged', 'transferred'])->count(),
        ]);
    }

    public function listReports()
    {
        $query = VerificationReport::visibleTo(request()->user())->with(['creator', 'complaint.verification']);

        $reports = $query->latest()->paginate(15);

        if (request()->expectsJson()) {
            return response()->json($reports);
        }

        return view('verifications.reports-list', compact('reports'));
    }

    public function downloadPdf(VerificationReport $report)
    {
        abort_unless(
            VerificationReport::visibleTo(request()->user())->whereKey($report->id)->exists(),
            404
        );

        $report->load('creator', 'complaint');

        $pdf = Pdf::loadView('verifications.reports-pdf', compact('report'));

        $filename = 'verification-report-' . str_replace('/', '-', $report->tracking_no) . '.pdf';

        return $pdf->download($filename);
    }

    public function reports()
    {
        $complaints = Complaint::visibleTo(request()->user())->whereNotNull('tracking_no')
            ->orderBy('tracking_no')
            ->get(['id', 'tracking_no', 'complainant_name', 'cnic', 'contact_no']);

        $crimeCategories = OffenceType::orderBy('name')->get(['name', 'value']);

        return view('verifications.reports', compact('complaints', 'crimeCategories'));
    }

    public function storeReport(Request $request)
    {
        try {
            $data = $request->validate([
                'complaint_id'        => 'nullable|exists:complaints,id',
                'tracking_no'         => 'required|string|max:255',
                'registration_at'     => 'nullable|date',
                'assignment_date'     => 'nullable|date',
                'verification_date'   => 'nullable|date',

                'victim_name'         => 'required|string|max:255',
                'victim_father_name'  => 'nullable|string|max:255',
                'victim_occupation'   => 'nullable|string|max:255',
                'victim_gender'       => 'nullable|string|in:male,female,other,Male,Female,Other',
                'victim_cnic'         => ['required', 'string', 'regex:/^(\d{5}-\d{7}-\d{1}|\d{13})$/'],
                'victim_country_code' => 'nullable|string|max:8',
                'victim_phone'        => 'required|string|max:20',
                'victim_email'        => 'nullable|email|max:255',

                'crime_category'      => 'required|string|max:255',
                'crime_description'   => 'nullable|string|max:5000',
                'city'                => 'required|string|max:255',

                'accused_known'       => 'required|boolean',
                'accused'             => 'nullable|array',
                'accused.*.name'      => 'nullable|string|max:255',
                'accused.*.father_name' => 'nullable|string|max:255',
                'accused.*.phone'     => 'nullable|string|max:20',
                'accused.*.email'     => 'nullable|email|max:255',
                'accused.*.cnic'      => ['nullable', 'string', 'regex:/^(\d{5}-\d{7}-\d{1}|\d{13})$/'],
                'accused.*.address'   => 'nullable|string|max:1000',
                'accused.*.post_address' => 'nullable|string|max:1000',
                'accused.*.nationality' => 'nullable|string|max:50',
                'accused.*.passport_no' => 'nullable|string|max:50|required_if:accused.*.nationality,Dual Nationality Holder|required_if:accused.*.nationality,Foreigner',
                'accused.*.photo'     => 'nullable|string',
                'accused.*.cnic_front' => 'nullable|string',
                'accused.*.cnic_back' => 'nullable|string',
                'accused.*.passport_attachment' => 'nullable|string',
                'accused.*.picture' => 'nullable|string',
                'accused.*.other_attachment' => 'nullable|string',
                'accused.*.country_code' => 'nullable|string|max:8',

                'recommendation_short' => 'nullable|string|max:2000',
                'recommendation_full'  => 'nullable|string|max:10000',
                'comments'             => 'nullable|string|max:5000',
                'recommendation'       => 'nullable|string|in:enquiry_registration,closure,merge,transfer',
                'closure_reason'       => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence',

                'inquiry_no'          => 'nullable|string|max:255',
                'case_no'             => 'nullable|string|max:255',
                'evidence_file'       => 'nullable|array',
                'evidence_file.*'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'evidence_desc'       => 'nullable|array',
                'existing_evidence'   => 'nullable|array',
                'transaction_bank'    => 'nullable|array',
                'transaction_account' => 'nullable|array',
                'transaction_amount'  => 'nullable|array',
                'transaction_date'    => 'nullable|array',
                'transaction_file'    => 'nullable|array',
                'transaction_file.*'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'existing_transactions' => 'nullable|array',
            ]);

            if (!empty($data['victim_gender'])) {
                $data['victim_gender'] = strtolower($data['victim_gender']);
            }

            // Handle evidence uploads: files + per-file description
            $evidence = [];
            $evidenceFiles = $request->file('evidence_file', []);
            $evidenceDesc  = $request->input('evidence_desc', []);
            if (is_array($evidenceFiles)) {
                foreach ($evidenceFiles as $index => $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store('verification-reports/evidence', 'public');
                        $evidence[] = [
                            'file'        => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'description' => $evidenceDesc[$index] ?? null,
                        ];
                    }
                }
            }
            $data['evidence'] = $evidence ?: null;

            $transactions = [];
            \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $request->input('existing_transactions', []), 'existing_transactions');
            foreach ($request->input('existing_transactions', []) as $t) {
                if (is_array($t) && (!empty($t['bank']) || !empty($t['account']) || !empty($t['amount']))) {
                    $transactions[] = [
                        'bank'    => $t['bank'] ?? null,
                        'account' => $t['account'] ?? null,
                        'amount'  => $t['amount'] ?? null,
                        'date'    => $t['date'] ?? null,
                        'file'    => $t['file'] ?? null,
                    ];
                }
            }
            
            $tBanks   = $request->input('transaction_bank', []);
            $tAccs    = $request->input('transaction_account', []);
            $tAmts    = $request->input('transaction_amount', []);
            $tDates   = $request->input('transaction_date', []);
            $tFiles   = $request->file('transaction_file', []);
            
            if (is_array($tBanks)) {
                foreach ($tBanks as $idx => $bank) {
                    if (!empty($bank) || !empty($tAccs[$idx]) || !empty($tAmts[$idx])) {
                        $attachment = null;
                        if (isset($tFiles[$idx]) && $tFiles[$idx] instanceof \Illuminate\Http\UploadedFile) {
                            $attachment = $tFiles[$idx]->store('verification-reports/transactions', 'public');
                        }
                        $transactions[] = [
                            'bank'    => $bank,
                            'account' => $tAccs[$idx] ?? null,
                            'amount'  => $tAmts[$idx] ?? null,
                            'date'    => $tDates[$idx] ?? null,
                            'file'    => $attachment,
                        ];
                    }
                }
            }
            $data['transactions'] = $transactions ?: null;

            // Handle accused identity uploads (photo, CNIC, passport, picture)
            $accusedData = $data['accused'] ?? [];
            if (is_array($accusedData) && $accusedData !== []) {
                $data['accused'] = $this->applyAccusedIdentityFiles(
                    $request,
                    $accusedData,
                    'verification-reports/accused-photos'
                );
            } else {
                $data['accused'] = !empty($accusedData) ? $accusedData : null;
            }

            // Auto-use user's profile signature if available
            $user = auth()->user();
            if ($user && $user->signature) {
                $data['signature'] = $user->signature;
            }

            $data['created_by'] = auth()->id();

            // Ensure linked complaint belongs to VO's circle so same-circle CI can see it
            if (!empty($data['complaint_id'])) {
                $complaint = Complaint::find($data['complaint_id']);
                if ($complaint && !$complaint->circle_id && $user?->circle_id) {
                    $complaint->update(['circle_id' => $user->circle_id]);
                }
            }

            unset(
                $data['transaction_bank'],
                $data['transaction_account'],
                $data['transaction_amount'],
                $data['transaction_date'],
                $data['transaction_file'],
                $data['existing_transactions'],
                $data['evidence_file'],
                $data['evidence_desc'],
                $data['existing_evidence']
            );

            $report = VerificationReport::create($data);

            // Same-circle CI sees this report; also ping if linked verification exists
            if ($report->complaint_id) {
                try {
                    $linked = Verification::with(['complaint', 'officer'])
                        ->where('complaint_id', $report->complaint_id)
                        ->latest('id')
                        ->first();
                    if ($linked) {
                        $this->notifyCircleInchargesForVerification($linked);
                    }
                } catch (\Throwable $ne) {
                    \Log::warning('Verification notification failed: ' . $ne->getMessage());
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Verification report saved successfully',
                    'data' => $report->fresh(),
                ], 201);
            }

            return redirect()->route('verifications.reports')
                ->with('success', 'Verification report saved successfully');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            \Log::error('storeReport error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to save verification report: ' . \App\Services\ClientError::message($e),
                ], 500);
            }
            return back()->withInput()->with('error', 'Failed to save report: ' . \App\Services\ClientError::message($e));
        }
    }

    public function showReport(VerificationReport $report)
    {
        abort_unless(
            VerificationReport::visibleTo(request()->user())->whereKey($report->id)->exists(),
            404
        );

        $payload = $report->load('creator', 'complaint')->toArray();
        $payload['accused'] = $this->accusedWithUrls($report->accused, $report);

        return response()->json($payload);
    }

    public function destroyReport(VerificationReport $report)
    {
        abort_unless(
            VerificationReport::visibleTo(request()->user())->whereKey($report->id)->exists(),
            404
        );

        $user = request()->user();
        abort_unless(
            $user->hasRole('admin'),
            403,
            'Officers are not allowed to delete verification reports. Contact Administrator.'
        );

        $report->delete();

        return response()->json(['message' => 'Verification report deleted successfully']);
    }

    public function bulkDestroyReports(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole('admin'),
            403,
            'Officers are not allowed to delete verification reports. Contact Administrator.'
        );

        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:verification_reports,id',
        ]);

        $query = VerificationReport::visibleTo($user)->whereIn('id', $data['ids']);
        $count = $query->delete();

        return response()->json([
            'message' => $count . ' verification report(s) deleted',
            'deleted' => $count,
        ]);
    }

    public function updateReport(Request $request, VerificationReport $report)
    {
        abort_unless(
            VerificationReport::visibleTo(request()->user())->whereKey($report->id)->exists(),
            404
        );

        try {
            $data = $request->validate([
                'complaint_id'        => 'nullable|exists:complaints,id',
                'tracking_no'         => 'required|string|max:255',
                'registration_at'     => 'nullable|date',
                'assignment_date'     => 'nullable|date',
                'verification_date'   => 'nullable|date',

                'victim_name'         => 'required|string|max:255',
                'victim_father_name'  => 'nullable|string|max:255',
                'victim_occupation'   => 'nullable|string|max:255',
                'victim_gender'       => 'nullable|string|in:male,female,other,Male,Female,Other',
                'victim_cnic'         => ['required', 'string', 'regex:/^(\d{5}-\d{7}-\d{1}|\d{13})$/'],
                'victim_country_code' => 'nullable|string|max:8',
                'victim_phone'        => 'required|string|max:20',
                'victim_email'        => 'nullable|email|max:255',

                'crime_category'      => 'required|string|max:255',
                'crime_description'   => 'nullable|string|max:5000',
                'city'                => 'required|string|max:255',

                'accused_known'       => 'required|boolean',
                'accused'             => 'nullable|array',
                'accused.*.name'      => 'nullable|string|max:255',
                'accused.*.father_name' => 'nullable|string|max:255',
                'accused.*.phone'     => 'nullable|string|max:20',
                'accused.*.email'     => 'nullable|email|max:255',
                'accused.*.cnic'      => ['nullable', 'string', 'regex:/^(\d{5}-\d{7}-\d{1}|\d{13})$/'],
                'accused.*.address'   => 'nullable|string|max:1000',
                'accused.*.post_address' => 'nullable|string|max:1000',
                'accused.*.nationality' => 'nullable|string|max:50',
                'accused.*.passport_no' => 'nullable|string|max:50|required_if:accused.*.nationality,Dual Nationality Holder|required_if:accused.*.nationality,Foreigner',
                'accused.*.photo'     => 'nullable|string',
                'accused.*.cnic_front' => 'nullable|string',
                'accused.*.cnic_back' => 'nullable|string',
                'accused.*.passport_attachment' => 'nullable|string',
                'accused.*.picture' => 'nullable|string',
                'accused.*.other_attachment' => 'nullable|string',
                'accused.*.country_code' => 'nullable|string|max:8',

                'recommendation_short' => 'nullable|string|max:2000',
                'recommendation_full'  => 'nullable|string|max:10000',
                'comments'             => 'nullable|string|max:5000',
                'recommendation'       => 'nullable|string|in:enquiry_registration,closure,merge,transfer',
                'closure_reason'       => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence',

                'inquiry_no'          => 'nullable|string|max:255',
                'case_no'             => 'nullable|string|max:255',
                'evidence_file'       => 'nullable|array',
                'evidence_file.*'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'evidence_desc'       => 'nullable|array',
                'existing_evidence'   => 'nullable|array',
                'transaction_bank'    => 'nullable|array',
                'transaction_account' => 'nullable|array',
                'transaction_amount'  => 'nullable|array',
                'transaction_date'    => 'nullable|array',
                'transaction_file'    => 'nullable|array',
                'transaction_file.*'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'existing_transactions' => 'nullable|array',
            ]);

            if (!empty($data['victim_gender'])) {
                $data['victim_gender'] = strtolower($data['victim_gender']);
            }

            \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $request->input('existing_evidence', []));
            // Existing evidence (kept from previous state), then newly uploaded files
            $evidence = [];
            foreach ($request->input('existing_evidence', []) as $ev) {
                if (is_array($ev) && !empty($ev['file_path'])) {
                    $evidence[] = [
                        'file'          => $ev['file_path'],
                        'original_name' => $ev['original_name'] ?? null,
                        'description'   => $ev['description'] ?? null,
                    ];
                }
            }

            $evidenceFiles = $request->file('evidence_file', []);
            $evidenceDesc  = $request->input('evidence_desc', []);
            if (is_array($evidenceFiles)) {
                foreach ($evidenceFiles as $index => $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        $path = $file->store('verification-reports/evidence', 'public');
                        $evidence[] = [
                            'file'          => $path,
                            'original_name' => $file->getClientOriginalName(),
                            'description'   => $evidenceDesc[$index] ?? null,
                        ];
                    }
                }
            }
            $data['evidence'] = $evidence ?: null;

            $transactions = [];
            \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $request->input('existing_transactions', []), 'existing_transactions');
            foreach ($request->input('existing_transactions', []) as $t) {
                if (is_array($t) && (!empty($t['bank']) || !empty($t['account']) || !empty($t['amount']))) {
                    $transactions[] = [
                        'bank'    => $t['bank'] ?? null,
                        'account' => $t['account'] ?? null,
                        'amount'  => $t['amount'] ?? null,
                        'date'    => $t['date'] ?? null,
                        'file'    => $t['file'] ?? null,
                    ];
                }
            }
            
            $tBanks   = $request->input('transaction_bank', []);
            $tAccs    = $request->input('transaction_account', []);
            $tAmts    = $request->input('transaction_amount', []);
            $tDates   = $request->input('transaction_date', []);
            $tFiles   = $request->file('transaction_file', []);
            
            if (is_array($tBanks)) {
                foreach ($tBanks as $idx => $bank) {
                    if (!empty($bank) || !empty($tAccs[$idx]) || !empty($tAmts[$idx])) {
                        $attachment = null;
                        if (isset($tFiles[$idx]) && $tFiles[$idx] instanceof \Illuminate\Http\UploadedFile) {
                            $attachment = $tFiles[$idx]->store('verification-reports/transactions', 'public');
                        }
                        $transactions[] = [
                            'bank'    => $bank,
                            'account' => $tAccs[$idx] ?? null,
                            'amount'  => $tAmts[$idx] ?? null,
                            'date'    => $tDates[$idx] ?? null,
                            'file'    => $attachment,
                        ];
                    }
                }
            }
            $data['transactions'] = $transactions ?: null;

            // Accused identity uploads (photo, CNIC, passport, picture)
            $accusedData = $data['accused'] ?? [];
            if (is_array($accusedData) && $accusedData !== []) {
                $data['accused'] = $this->applyAccusedIdentityFiles(
                    $request,
                    $accusedData,
                    'verification-reports/accused-photos'
                );
            } else {
                $data['accused'] = !empty($accusedData) ? $accusedData : null;
            }

            unset(
                $data['transaction_bank'],
                $data['transaction_account'],
                $data['transaction_amount'],
                $data['transaction_date'],
                $data['transaction_file'],
                $data['existing_transactions'],
                $data['evidence_file'],
                $data['evidence_desc'],
                $data['existing_evidence']
            );

            $report->update($data);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Verification report updated successfully',
                    'data' => $report->fresh(),
                ]);
            }

            return redirect()->route('verifications.reports')
                ->with('success', 'Verification report updated successfully');
        } catch (\Illuminate\Validation\ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            \Log::error('updateReport error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to update verification report: ' . \App\Services\ClientError::message($e),
                ], 500);
            }
            return back()->withInput()->with('error', 'Failed to update report: ' . \App\Services\ClientError::message($e));
        }
    }

    public function show(Verification $verification)
    {
        abort_unless(
            Verification::visibleTo(request()->user())->whereKey($verification->id)->exists(),
            404
        );

        if (request()->expectsJson()) {
            $payload = $verification->load('complaint', 'officer', 'sentByUser')->toArray();
            $payload['accused'] = $this->accusedWithUrls($verification->accused, $verification);

            return response()->json($payload);
        }
        return redirect()->route('verifications.edit', $verification);
    }

    public function create()
    {
        $complaints = Complaint::where('status', 'complete')
            ->whereDoesntHave('verification')
            ->get();

        $officers = User::role('verification_officer')->get();
        $circles = Circle::all();

        return view('verifications.create', compact('complaints', 'officers', 'circles'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Verification::class);

        if (is_string($request->input('direct_info'))) {
            $decodedDirect = json_decode($request->input('direct_info'), true);
            if (is_array($decodedDirect)) {
                $request->merge(['direct_info' => $decodedDirect]);
            }
        }
        if (is_string($request->input('accused'))) {
            $decodedAccused = json_decode($request->input('accused'), true);
            if (is_array($decodedAccused)) {
                $request->merge(['accused' => $decodedAccused]);
            }
        }

        $data = $request->validate([
            'complaint_id'           => 'nullable|exists:complaints,id',
            'direct_info'            => 'nullable|array',
            'accused'                => 'nullable',
            'verification_officer_id'=> 'required|exists:users,id',
            'priority_type'          => 'required|in:normal,high,critical',
            'report_text'            => 'nullable|string|max:5000',
        ]);

        if (empty($data['complaint_id']) && empty($data['direct_info']['reference_no'] ?? null)) {
            return response()->json([
                'message' => 'Select a complaint or provide direct case details (reference no).',
                'errors'  => ['direct_info' => ['Reference No is required for direct case.']],
            ], 422);
        }

        $user = $request->user();
        // Verification officers may only create VIP/direct cases assigned to themselves
        if ($user->hasRole('verification_officer')
            && !$user->hasAnyRole(['admin', 'circle_incharge', 'operator', 'director_general'])) {
            if (empty($data['direct_info']['reference_no'] ?? null)) {
                return response()->json([
                    'message' => 'Verification officers can only create VIP / Direct verifications.',
                ], 403);
            }
            $data['verification_officer_id'] = $user->id;
        }

        $data['status']      = 'assigned';
        $data['assigned_by'] = auth()->id();
        $data['assigned_at'] = now();

        $accused = $this->parseAccusedInput($request);
        if ($accused !== null) {
            $data['accused'] = $this->applyAccusedIdentityFiles($request, $accused, 'verifications/accused');
        } else {
            unset($data['accused']);
        }

        $verification = Verification::create($data);

        app(OfficerAssignmentService::class)->recordInitial(
            $verification,
            OfficerAssignmentService::TYPE_VERIFICATION,
            OfficerAssignmentService::ROLE_VO,
            (int) $verification->verification_officer_id,
            auth()->id(),
        );

        try {
            $verification->officer?->notify(new VerificationAssignedNotification($verification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('VerificationAssignedNotification failed: ' . $e->getMessage());
        }

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

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Verification assigned successfully', 'data' => $verification->load('officer', 'complaint')], 201);
        }

        return redirect()->route('verifications.index')
            ->with('success', 'Verification assigned successfully');
    }

    public function edit(Verification $verification)
    {
        $officers = User::role('verification_officer')->get();
        $circles = Circle::all();
        $complaints = Complaint::where('status', 'complete')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'tracking_no', 'diary_no', 'complainant_name']);

        return view('verifications.edit', compact('verification', 'officers', 'circles', 'complaints'));
    }

    public function update(Request $request, Verification $verification)
    {
        if ($locked = $this->denyIfForwardLocked($verification, $request)) {
            return $locked;
        }
        if ($stale = $this->denyIfStale($verification, $request)) {
            return $stale;
        }
        $this->authorize('update', $verification);

        $rules = [
            'verification_officer_id' => 'required|exists:users,id',
            'priority_type'           => 'required|in:normal,high,critical',
            'status'                  => 'nullable|in:assigned,in_progress,submitted,sent_back',
            'recommendation'          => 'nullable|string|max:5000',
            'closure_reason'          => 'nullable|in:non_pursuance,irrelevant,invalid,lack_of_evidence',
            'merge_complaint_id'      => 'nullable|integer|exists:complaints,id',
            'transfer_department'     => 'nullable|string|max:255',
            'transfer_circle_id'      => 'nullable|exists:circles,id',
            'report_text'             => 'nullable|string|max:10000',
            'complainant_message'     => 'nullable|string|max:2000',
            'appeared_at'             => 'nullable|date',
            'message_via'             => 'nullable|string|in:whatsapp,sms,phone,in_app,email',
            'whatsapp_sent_at'        => 'nullable|date',
            'direct_info'             => 'nullable|array',
            'accused'                 => 'nullable',
        ];

        if (is_string($request->input('direct_info'))) {
            $decodedDirect = json_decode($request->input('direct_info'), true);
            if (is_array($decodedDirect)) {
                $request->merge(['direct_info' => $decodedDirect]);
            }
        }
        if (is_string($request->input('accused'))) {
            $decodedAccused = json_decode($request->input('accused'), true);
            if (is_array($decodedAccused)) {
                $request->merge(['accused' => $decodedAccused]);
            }
        }

        $data = $request->validate($rules);

        $user = $request->user();

        // Only supervisors may reassign the verification officer.
        if (!$user->hasAnyRole(['admin', 'circle_incharge'])) {
            $data['verification_officer_id'] = $verification->verification_officer_id;
        }

        if (!empty($data['recommendation'])) {
            $data['submitted_at'] = now();
        }

        // Recording a complainant message / appear-date means the verification
        // officer has contacted the complainant. Stamp who recorded it and ring
        // the circle officer so they stay in the loop.
        $justNotified = (empty($verification->complainant_message) && !empty($data['complainant_message']))
                     || (empty($verification->appeared_at) && !empty($data['appeared_at']));
        if ($justNotified) {
            $data['sent_by'] = auth()->id();
        }

        if (!empty($data['message_via']) && $data['message_via'] === 'whatsapp' && empty($verification->whatsapp_sent_at)) {
            $data['whatsapp_sent_at'] = $data['whatsapp_sent_at'] ?? now();
        }

        $officerChanged = !empty($data['verification_officer_id'])
            && (int) $data['verification_officer_id'] !== $verification->verification_officer_id;

        $accused = $this->parseAccusedInput($request);
        unset($data['accused']);
        if ($accused !== null) {
            $data['accused'] = $this->applyAccusedIdentityFiles($request, $accused, 'verifications/accused');
        }

        if ($officerChanged) {
            $verification->load('officer');
            app(OfficerAssignmentService::class)->reassign(
                $verification,
                OfficerAssignmentService::TYPE_VERIFICATION,
                OfficerAssignmentService::ROLE_VO,
                (int) $data['verification_officer_id'],
                $request->user()->id,
                'Officer changed via update',
            );
        }

        $verification->update($data);

        if ($officerChanged) {
            try {
                $verification->officer?->notify(new VerificationAssignedNotification($verification));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('VerificationAssignedNotification failed: ' . $e->getMessage());
            }

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
        } else {
            app(OfficerAssignmentService::class)->touchWorkSnapshot(
                $verification->fresh(),
                OfficerAssignmentService::TYPE_VERIFICATION,
            );
        }

        if ($justNotified) {
            $this->notifyCircleInchargeAboutComplainantMessage($verification);
        }

        if ($request->expectsJson()) {
            $fresh = $verification->fresh();
            $payload = $fresh->toArray();
            $payload['accused'] = $this->accusedWithUrls($fresh->accused, $fresh);

            return response()->json(['message' => 'Verification updated successfully', 'data' => $payload]);
        }

        return redirect()->route('verifications.index')
            ->with('success', 'Verification updated successfully');
    }

    /**
     * Quick action: save appearance message and return WhatsApp deep-link.
     */
    public function notifyComplainant(Request $request, Verification $verification, ComplainantNotifyService $notify)
    {
        $this->authorize('update', $verification);

        $data = $request->validate([
            'appeared_at'         => 'nullable|date',
            'complainant_message' => 'nullable|string|max:2000',
            'message_via'         => 'nullable|string|in:whatsapp,sms,phone,in_app,email',
        ]);

        if (!empty($data['appeared_at'])) {
            $verification->appeared_at = $data['appeared_at'];
        }

        $message = trim((string) ($data['complainant_message'] ?? ''));
        if ($message === '') {
            $message = $notify->appearanceMessage($verification->loadMissing('complaint', 'officer'), $request->user());
        }

        $via = $data['message_via'] ?? 'whatsapp';
        $verification->fill([
            'complainant_message' => $message,
            'message_via'         => $via,
            'sent_by'             => $request->user()->id,
            'whatsapp_sent_at'    => $via === 'whatsapp' ? now() : $verification->whatsapp_sent_at,
        ])->save();

        $complaint = $verification->complaint;

        // Send the appearance notice as SMS whenever an SMS/WhatsApp message is recorded.
        if ($complaint?->contact_no) {
            $phone = preg_replace('/\D+/', '', ($complaint->contact_country_code ?: '+92') . $complaint->contact_no);
            if ($phone) {
                app(SmsService::class)->sendBilingual(
                    $phone,
                    SmsTemplates::appearanceNotice($verification->loadMissing('officer'), $request->user(), 'en'),
                    SmsTemplates::appearanceNotice($verification->loadMissing('officer'), $request->user(), 'ur'),
                    [
                        'country_code'   => $complaint->contact_country_code ?: '+92',
                        'recipient_type' => 'complainant',
                        'subject_type'   => 'verification',
                        'subject_id'     => $verification->id,
                        'trigger'        => 'appearance_notice',
                    ]
                );
            }
        }

        $whatsappUrl = $notify->whatsappUrl($complaint, $message);

        // Keep circle incharge in the loop
        if ($complaint?->circle_id) {
            $circleUsers = User::role('circle_incharge')->where('circle_id', $complaint->circle_id)->get();
            foreach ($circleUsers as $cu) {
                $cu->notify(new ComplainantMessageNotification($verification));
            }
        }

        return response()->json([
            'message' => 'Complainant message ready',
            'data' => $verification->fresh()->load('complaint', 'officer', 'sentByUser'),
            'complainant_notify' => [
                'message'      => $message,
                'via'          => $via,
                'whatsapp_url' => $whatsappUrl,
                'phone'        => $notify->phoneDigits($complaint),
            ],
        ]);
    }

    public function destroy(Verification $verification)
    {
        $this->authorize('delete', $verification);

        activity()->useLog('verifications')
            ->performedOn($verification)
            ->causedBy(auth()->user())
            ->withProperties(['id' => $verification->id])
            ->log('Verification deleted: #' . $verification->id);

        $verification->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Verification deleted successfully']);
        }

        return redirect()->route('verifications.index')
            ->with('success', 'Verification deleted successfully');
    }

    /**
     * Bulk-close selected verifications (circle officers / admins only).
     * A closed verification is finalized and removed from active workflow.
     */
    public function bulkClose(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['admin', 'circle_incharge', 'verification_officer']), 403);

        $data = $request->validate([
            'ids'            => 'required|array|min:1',
            'ids.*'          => 'integer|exists:verifications,id',
            'closure_reason' => 'nullable|in:non_pursuance,irrelevant,invalid,lack_of_evidence',
        ]);

        // Only IDs the caller is allowed to see can be closed (prevents IDOR).
        $count = Verification::visibleTo($user)
            ->whereIn('id', $data['ids'])
            ->whereNotIn('status', ['approved', 'closed'])
            ->update([
                'status'         => 'closed',
                'closure_reason' => $data['closure_reason'] ?? null,
                'completed_at'   => now(),
            ]);

        activity()->useLog('verifications')
            ->causedBy($user)
            ->withProperties(['ids' => $data['ids'], 'closure_reason' => $data['closure_reason'] ?? null])
            ->log("Bulk closed {$count} verification(s)");

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Closed {$count} verification(s)",
                'count'   => $count,
            ]);
        }

        return back()->with('success', "Closed {$count} verification(s)");
    }

    /**
     * Bulk process selected verifications (admin / circle incharge only):
     * closure, merge, transfer, or delete. Closure/merge/transfer also update
     * the linked complaint, matching the approve() downstream behaviour.
     */
    public function bulkAction(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasAnyRole(['admin', 'circle_incharge', 'verification_officer']), 403);

        $data = $request->validate([
            'ids'                 => 'required|array|min:1',
            'ids.*'               => 'integer|exists:verifications,id',
            'action'              => 'required|string|in:closure,merge,transfer,delete,proceed_to_verification',
            'closure_reason'      => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence',
            'merge_complaint_id'  => 'nullable|integer|exists:complaints,id',
            'transfer_department' => 'nullable|string|max:255',
            'transfer_circle_id'  => 'nullable|integer|exists:circles,id',
        ]);

        // Merge action must not target the verification being merged.
        if ($data['action'] === 'merge' && in_array($data['merge_complaint_id'] ?? null, $data['ids'])) {
            return response()->json(['message' => 'Cannot merge a verification with itself.'], 422);
        }

        if ($data['action'] === 'delete') {
            abort_unless($user->hasRole('admin'), 403, 'Officers are not allowed to delete verifications.');
        }

        $verifications = Verification::visibleTo($user)
            ->with('complaint')
            ->whereIn('id', $data['ids'])
            ->get();

        if ($verifications->isEmpty()) {
            return response()->json(['message' => 'No verifications found for the selected records.'], 404);
        }

        DB::transaction(function () use ($verifications, $data, $user) {
            foreach ($verifications as $verification) {
                $complaint = $verification->complaint;

                switch ($data['action']) {
                    case 'closure':
                        $verification->update([
                            'status'         => 'closed',
                            'completed_at'   => now(),
                            'recommendation' => 'closure',
                            'closure_reason' => $data['closure_reason'] ?? null,
                        ]);
                        $complaint?->update([
                            'status'         => 'closed',
                            'final_status'   => 'closed',
                            'closure_reason' => $data['closure_reason'] ?? null,
                        ]);
                        break;

                    case 'merge':
                        $verification->update([
                            'status'             => 'approved',
                            'approved_at'        => now(),
                            'recommendation'     => 'merge',
                            'merge_complaint_id' => $data['merge_complaint_id'] ?? null,
                        ]);
                        $complaint?->update([
                            'status'         => 'merged',
                            'final_status'   => 'merged',
                            'merged_with_id' => $data['merge_complaint_id'] ?? null,
                        ]);
                        break;

                    case 'transfer':
                        $verification->update([
                            'status'              => 'approved',
                            'approved_at'         => now(),
                            'recommendation'      => 'transfer',
                            'transfer_department' => $data['transfer_department'] ?? null,
                            'transfer_circle_id'  => $data['transfer_circle_id'] ?? null,
                        ]);
                        $complaint?->update([
                            'status'                 => 'transferred',
                            'final_status'           => 'transferred',
                            'transfer_to_department' => $data['transfer_department'] ?? null,
                            'transfer_to_circle_id'  => $data['transfer_circle_id'] ?? null,
                        ]);
                        break;

                    case 'delete':
                        activity()->useLog('verifications')
                            ->performedOn($verification)
                            ->causedBy($user)
                            ->withProperties(['id' => $verification->id])
                            ->log('Verification deleted (bulk): #' . $verification->id);
                        $verification->delete();
                        break;

                    case 'proceed_to_verification':
                        $verification->update([
                            'status'          => 'submitted',
                            'submitted_at'    => now(),
                            'recommendation'  => 'enquiry_registration',
                        ]);
                        break;
                }
            }
        });

        $actionLabel = [
            'closure' => 'closed',
            'proceed_to_verification' => 'proceeded to verification (enquiry registration)',
        ][$data['action']] ?? $data['action'];

        return response()->json([
            'message' => $data['action'] === 'delete'
                ? count($verifications) . ' verification(s) deleted successfully.'
                : count($verifications) . ' verification(s) marked as ' . $actionLabel . '.',
        ]);
    }

    // ── Existing API methods ──

    /**
     * Notify the circle officer(s) that a verification officer recorded a
     * complainant message (i.e. the complainant was contacted / appeared).
     */
    protected function notifyCircleInchargeAboutComplainantMessage(Verification $verification): void
    {
        try {
            $circleId = $verification->complaint?->circle_id
                ?: ($verification->direct_info['circle_id'] ?? null)
                ?: $verification->officer?->circle_id;

            if (!$circleId) {
                return;
            }

            $cis = collect();
            try {
                $cis = User::role('circle_incharge')->where('circle_id', $circleId)->get();
            } catch (\Throwable $e) {}

            if ($cis->isEmpty()) {
                $cis = User::where('role', 'circle_incharge')->where('circle_id', $circleId)->get();
            }

            foreach ($cis as $cu) {
                try {
                    $cu->notify(new ComplainantMessageNotification($verification));
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            \Log::warning('notifyCircleInchargeAboutComplainantMessage failed: ' . $e->getMessage());
        }
    }

    /**
     * Ali (VO, Lahore Circle) submits → only Aslam (CI, Lahore Circle) is notified.
     */
    protected function notifyCircleInchargesForVerification(Verification $verification): void
    {
        try {
            $circleId = $verification->complaint?->circle_id
                ?: ($verification->direct_info['circle_id'] ?? null)
                ?: $verification->officer?->circle_id;

            if (!$circleId) {
                return;
            }

            $cis = collect();
            try {
                $cis = User::role('circle_incharge')->where('circle_id', $circleId)->get();
            } catch (\Throwable $e) {}

            if ($cis->isEmpty()) {
                $cis = User::where('role', 'circle_incharge')->where('circle_id', $circleId)->get();
            }

            foreach ($cis as $cu) {
                try {
                    $cu->notify(new VerificationSubmittedNotification($verification));
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {
            \Log::warning('notifyCircleInchargesForVerification failed: ' . $e->getMessage());
        }
    }

    public function assign(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'verification_officer_id' => 'required|integer|exists:users,id',
            'priority_type'           => 'nullable|string|in:normal,high,critical',
            'assigned_at'             => 'nullable|date',
        ]);

        $data['complaint_id'] = $complaint->id;
        $data['status']       = 'assigned';
        $data['assigned_at']  ??= now();

        $officer = User::find($data['verification_officer_id']);
        if (!$complaint->circle_id && $officer?->circle_id) {
            $complaint->update(['circle_id' => $officer->circle_id]);
        } elseif (!$complaint->circle_id && $request->user()?->circle_id) {
            $complaint->update(['circle_id' => $request->user()->circle_id]);
        }

        $verification = Verification::create($data);

        app(OfficerAssignmentService::class)->recordInitial(
            $verification,
            OfficerAssignmentService::TYPE_VERIFICATION,
            OfficerAssignmentService::ROLE_VO,
            (int) $verification->verification_officer_id,
            $request->user()->id,
        );

        try {
            $verification->officer?->notify(new VerificationAssignedNotification($verification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('VerificationAssignedNotification failed: ' . $e->getMessage());
        }

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

        return response()->json([
            'message' => 'Verification assigned successfully',
            'data'    => $verification->load('officer'),
        ], 201);
    }

    public function submitReport(Request $request, Verification $verification)
    {
        $this->authorize('submit', $verification);

        $data = $request->validate([
            'report_text'        => 'required|string',
            'recommendation'     => 'required|string|in:enquiry_registration,closure,merge,transfer',
            'closure_reason'     => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence',
            'merge_complaint_id' => 'nullable|integer|exists:complaints,id',
            'transfer_department'=> 'nullable|string|max:255',
            'transfer_circle_id' => 'nullable|integer|exists:circles,id',
            'completed_at'       => 'nullable|date',
        ]);

        $data['status']       = 'submitted';
        $data['submitted_at'] = now();
        $data['completed_at'] ??= now();

        // Sync circle from VO so Lahore VO → Lahore Circle Incharge
        $complaint = $verification->complaint;
        $vo = $request->user();
        if ($complaint && !$complaint->circle_id && $vo?->circle_id) {
            $complaint->update(['circle_id' => $vo->circle_id]);
        }

        $verification->update($data);
        $verification->load(['complaint', 'officer', 'approvals']);

        app(OfficerAssignmentService::class)->touchWorkSnapshot(
            $verification,
            OfficerAssignmentService::TYPE_VERIFICATION,
        );

        // Only same-circle Circle Incharge(s) get this (e.g. aslam @ Lahore)
        $this->notifyCircleInchargesForVerification($verification);

        return response()->json([
            'message' => 'Verification report submitted to Circle Incharge',
            'data'    => $verification,
        ]);
    }

    public function approve(Request $request, Verification $verification)
    {
        $this->authorize('approve', $verification);

        $data = $request->validate([
            'decision'            => 'required|string|in:agree,review',
            'recommendation'      => 'required|string|in:enquiry_registration,closure,merge,transfer',
            'closure_reason'      => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence',
            'merge_complaint_id'  => 'nullable|integer|exists:complaints,id',
            'transfer_department' => 'nullable|string|max:255',
            'transfer_circle_id'  => 'nullable|integer|exists:circles,id',
            'enquiry_officer_id'  => 'nullable|integer|exists:users,id',
            'remarks'             => 'nullable|string|max:2000',
        ]);

        if ($data['decision'] === 'agree') {
            if ($data['recommendation'] === 'closure' && empty($data['closure_reason'])) {
                return response()->json(['message' => 'Closure reason is required.'], 422);
            }
            if ($data['recommendation'] === 'merge' && empty($data['merge_complaint_id'])) {
                return response()->json(['message' => 'Please select the complaint to merge with.'], 422);
            }
            if ($data['recommendation'] === 'transfer' && empty($data['transfer_department']) && empty($data['transfer_circle_id'])) {
                return response()->json(['message' => 'Transfer department or Circle is required.'], 422);
            }
            if ($data['recommendation'] === 'enquiry_registration' && empty($data['enquiry_officer_id'])) {
                return response()->json(['message' => 'Please select an Enquiry Officer before approving enquiry registration.'], 422);
            }
        }

        $approval = $verification->approvals()->create([
            'circle_incharge_id'  => $request->user()->id,
            'decision'            => $data['decision'],
            'recommendation'      => $data['recommendation'],
            'closure_reason'      => $data['closure_reason'] ?? null,
            'merge_complaint_id'  => $data['merge_complaint_id'] ?? null,
            'transfer_department' => $data['transfer_department'] ?? null,
            'transfer_circle_id'  => $data['transfer_circle_id'] ?? null,
            'remarks'             => $data['remarks'] ?? null,
        ]);

        $updateData = [
            'recommendation'      => $data['recommendation'],
            'closure_reason'      => $data['closure_reason'] ?? null,
            'merge_complaint_id'  => $data['merge_complaint_id'] ?? null,
            'transfer_department' => $data['transfer_department'] ?? null,
            'transfer_circle_id'  => $data['transfer_circle_id'] ?? null,
        ];

        // ── DOWNSTREAM ACTIONS on the Complaint ──
        $complaint = $verification->complaint;

        // Keep circle aligned so CI visibility stays consistent
        if ($complaint && !$complaint->circle_id && $request->user()?->circle_id) {
            $complaint->update(['circle_id' => $request->user()->circle_id]);
            $complaint->refresh();
        }

        if ($data['decision'] === 'agree') {
            $updateData['status']      = 'approved';
            $updateData['approved_at'] = now();

            switch ($data['recommendation']) {
                case 'closure':
                    DB::transaction(function () use ($complaint, $data) {
                        $complaint?->update([
                            'status'         => 'closed',
                            'final_status'   => 'closed',
                            'closure_reason' => $data['closure_reason'] ?? null,
                        ]);
                    });
                    break;

                case 'merge':
                    DB::transaction(function () use ($complaint, $data) {
                        $complaint?->update([
                            'status'         => 'merged',
                            'final_status'   => 'merged',
                            'merged_with_id' => $data['merge_complaint_id'] ?? null,
                        ]);
                    });
                    break;

                case 'transfer':
                    DB::transaction(function () use ($complaint, $data) {
                        $complaint?->update([
                            'status'                 => 'transferred',
                            'final_status'           => 'transferred',
                            'transfer_to_department' => $data['transfer_department'] ?? null,
                            'transfer_to_circle_id'  => $data['transfer_circle_id'] ?? null,
                        ]);
                    });
                    break;

                case 'enquiry_registration':
                    try {
                        DB::transaction(function () use ($complaint, $data, $verification) {
                            $circleId = $complaint?->circle_id
                                ?: ($verification->direct_info['circle_id'] ?? null)
                                ?: $verification->officer?->circle_id;
                            $circleCode = Circle::find($circleId)?->code;
                            $gen = app(EnquiryNumberGenerator::class);
                            $officerId = (int) $data['enquiry_officer_id'];
                            $enquiryAttrs = [
                                'complaint_id'       => $complaint?->id,
                                'direct_info'        => $complaint ? null : ($verification->direct_info ?? null),
                                'enquiry_number'     => $gen->generate($circleCode),
                                'status'             => 'assigned',
                                'reg_date'           => now(),
                                'assignment_date'    => now(),
                                'enquiry_officer_id' => $officerId,
                            ];
                            if (\Illuminate\Support\Facades\Schema::hasColumn('enquiries', 'priority')) {
                                $enquiryAttrs['priority'] = $complaint?->priority_type ?: 'normal';
                            }
                            $enquiry = Enquiry::create($enquiryAttrs);
                            app(\App\Services\EnquiryRecordHydrator::class)->hydrate($enquiry);
                            if ($complaint) {
                                $complaint->update([
                                    'status'       => 'enquiry_registered',
                                    'final_status' => 'enquiry_registered',
                                    'enquiry_id'   => $enquiry->id,
                                ]);
                            }
                        });
                    } catch (\Throwable $e) {
                        report($e);

                        return response()->json([
                            'message' => 'Enquiry registration failed: ' . \App\Services\ClientError::message($e),
                        ], 500);
                    }
                    break;
            }
        } else {
            $updateData['status'] = 'sent_back';
        }

        $verification->update($updateData);

        return response()->json([
            'message' => 'Verification ' . ($data['decision'] === 'agree' ? 'approved' : 'sent back for review'),
            'data'    => [
                'verification' => $verification->fresh()->load('complaint', 'officer', 'approvals'),
                'approval'     => $approval,
            ],
        ]);
    }

    public function changeOfficer(Request $request, Verification $verification)
    {
        abort_unless(
            $request->user()->hasAnyRole(['admin', 'circle_incharge'])
            || Verification::visibleTo($request->user())->whereKey($verification->id)->exists(),
            403
        );

        $data = $request->validate([
            'verification_officer_id' => 'required|integer|exists:users,id',
            'change_reason'           => 'nullable|string|max:500',
        ]);

        $verification->load('officer');

        app(OfficerAssignmentService::class)->reassign(
            $verification,
            OfficerAssignmentService::TYPE_VERIFICATION,
            OfficerAssignmentService::ROLE_VO,
            (int) $data['verification_officer_id'],
            $request->user()->id,
            $data['change_reason'] ?? 'Officer reassigned',
        );

        $verification->update([
            'verification_officer_id' => $data['verification_officer_id'],
            'status'                  => 'assigned',
            'assigned_at'             => now(),
            'assigned_by'             => $request->user()->id,
            // Clear live fields for the new officer; previous work is kept in officer_assignment_histories.work_snapshot
            'report_text'             => null,
            'recommendation'          => null,
            'closure_reason'          => null,
            'merge_complaint_id'      => null,
            'transfer_department'     => null,
            'transfer_circle_id'      => null,
            'submitted_at'            => null,
            'completed_at'            => null,
            'approved_at'             => null,
        ]);

        $verification->load('officer');
        try {
            $verification->officer?->notify(new VerificationAssignedNotification($verification));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('VerificationAssignedNotification failed: ' . $e->getMessage());
        }

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

        return response()->json([
            'message' => 'Verification officer reassigned. Previous officer work saved in history.',
            'data'    => $verification->fresh()->load('officer'),
        ]);
    }
}
