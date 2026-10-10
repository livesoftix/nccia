<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Circle;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\EnquiryLegalOpinion;
use App\Models\EnquiryApproval;
use App\Models\User;
use App\Models\EnquiryWitness;
use App\Models\EnquiryNotice;
use App\Models\EnquiryAccused;
use App\Models\EnquiryAttachment;
use App\Models\EnquiryRequisition;
use App\Notifications\EnquiryAssignedNotification;
use App\Notifications\NoticeNonAppearanceNotification;
use App\Notifications\CaseAssignedNotification;
use App\Services\EnquiryNumberGenerator;
use App\Services\EnquiryRecordHydrator;
use App\Services\FirNumberGenerator;
use App\Services\OfficerAssignmentService;
use App\Services\PrintService;
use App\Services\SeizeItemLock;
use App\Services\SmsService;
use App\Services\SmsTemplates;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnquiryController extends Controller
{
    use \App\Http\Controllers\Concerns\EnforcesWarrantApproval;
    use \App\Http\Controllers\Concerns\EnforcesStageLock;
    use \App\Http\Controllers\Concerns\PreventsLostUpdates;

    private const ALLOWED_STATUSES = 'registered,assigned,in_progress,pending,working,complete,cfr_submitted,legal_review_dd,legal_review_ad,legal_review_dg,approved,closed,transferred,converted_to_case,referred_court';

    private function canFillLegalAndApprove($user): bool
    {
        return $user && $user->hasAnyRole([
            'admin', 'circle_incharge', 'ad_legal', 'dd_legal',
            'additional_director', 'director_general',
        ]);
    }

    private function canUnlockSavedDiary($user): bool
    {
        return $user && $user->hasAnyRole(['admin', 'circle_incharge']);
    }

    private function keepExistingIds($relation, array &$keep, $user): void
    {
        if ($this->canUnlockSavedDiary($user)) {
            return;
        }
        foreach ($relation->pluck('id') as $id) {
            $keep[] = $id;
        }
    }

    /**
     * Move an uploaded file to public/uploads/<dir>.
     */
    private function moveFile(?UploadedFile $file, string $dir): ?string
    {
        if (!$file) {
            return null;
        }
        $ext = \App\Services\UploadSecurity::extension($file);
        $subdir = public_path('uploads/' . $dir);
        if (!is_dir($subdir) && !mkdir($subdir, 0755, true) && !is_dir($subdir)) {
            throw new \RuntimeException('Upload folder could not be created: ' . $dir);
        }
        $name = Str::random(24) . '.' . $ext;
        $file->move($subdir, $name);

        return 'uploads/' . $dir . '/' . $name;
    }

    private function uploadRules(): array
    {
        $rules = [];
        foreach (['activity_attachments', 'witness_attachments', 'witness_statements', 'accused_cnic_attachments',
            'accused_passport_attachments', 'accused_nadra_attachments', 'enquiry_attachment_files'] as $field) {
            $rules[$field] = 'sometimes|array|max:100';
            $rules[$field . '.*'] = 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:20480';
        }
        $rules['witness_pictures'] = 'sometimes|array|max:100';
        $rules['witness_pictures.*'] = 'nullable|image|mimes:jpg,jpeg,png|max:10240';
        return $rules;
    }

    private function decodeArrays(array &$data): void
    {
        foreach (['activities', 'legal_opinions', 'approvals', 'witnesses', 'notices', 'accused', 'attachments', 'requisitions'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if (is_string($data[$field])) {
                $decoded = json_decode($data[$field], true);
                $data[$field] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($data[$field])) {
                $data[$field] = [];
            }
        }
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncActivities(Enquiry $enquiry, array $activities, int $userId, Request $request): void
    {
        $keep = [];
        $actionLabels = [
            'dac_request'  => 'DAC Request',
            'bank_record'  => 'Bank Record Obtained',
            'search_seize'  => 'Search Warrant',
            'raid'          => 'Raid Permission',
            'arrest_warrant'=> 'Arrest Warrant',
            'notices'      => 'Summons Issued',
            'diaries'      => 'Diaries Maintained',
            'seizures'     => 'Seizures Made',
            'recoveries'   => 'Recoveries Effected',
            'cfr'          => 'CFR Submitted',
        ];

        $files = $request->file('activity_attachments', []);
        $forensicKeys = SeizeItemLock::forensicKeysForEnquiry((int) $enquiry->id);
        foreach ($activities as $i => $action) {
            $type = is_array($action) ? ($action['type'] ?? null) : $action;
            if (!$type) {
                continue;
            }
            $label = $actionLabels[$type] ?? $type;

            $existing = null;
            if (is_array($action) && !empty($action['id'])) {
                $existing = EnquiryActivity::find($action['id']);
                if ($existing && $existing->enquiry_id !== $enquiry->id) {
                    $existing = null;
                }
            }
            if (!$existing && is_array($action) && !empty($action['diary_no'])) {
                $existing = $enquiry->activities()->where('diary_no', $action['diary_no'])->first();
            }
            if (!$existing && is_array($action) && !empty($action['type']) && !empty($action['description'])) {
                $existing = $enquiry->activities()->where('type', $action['type'])->where('description', $action['description'])->first();
            }

            $meta = null;
            if (is_array($action)) {
                $meta = [];
                $incomingItems = !empty($action['seize_items']) && is_array($action['seize_items'])
                    ? $action['seize_items']
                    : [];
                $existingItems = [];
                if ($existing && is_array($existing->meta['seize_items'] ?? null)) {
                    $existingItems = $existing->meta['seize_items'];
                }
                $mergedItems = SeizeItemLock::merge(
                    SeizeItemLock::mapIncoming($incomingItems),
                    $existingItems,
                    $forensicKeys
                );
                if ($mergedItems !== []) {
                    $meta['seize_items'] = $mergedItems;
                }
                if (isset($action['analysis_scope'])) {
                    $meta['analysis_scope'] = $action['analysis_scope'];
                }
                if (isset($action['case_category'])) {
                    $meta['case_category'] = $action['case_category'];
                }
                foreach (['subject', 'kota', 'against_whom', 'scheduled_at'] as $key) {
                    if (array_key_exists($key, $action) && $action[$key] !== null && $action[$key] !== '') {
                        $meta[$key] = $action[$key];
                    }
                }
                if ($meta === []) {
                    $meta = null;
                }
            }

            $attrs = [
                'type'          => $type,
                'diary_no'      => (is_array($action) && !empty($action['diary_no'])) ? $action['diary_no'] : null,
                'description'   => (is_array($action) && !empty($action['description'])) ? $action['description'] : $label,
                'meta'          => $meta,
                'activity_date' => $this->normalizeDate(
                    (is_array($action) && !empty($action['activity_date'])) ? $action['activity_date'] : now()
                ) ?? now()->toDateString(),
            ];

            if ($existing && SeizeItemLock::activityHasLockedItems($existing, $forensicKeys)) {
                $attrs['type'] = $existing->type;
            }

            $file = $files[$i] ?? null;
            if ($file instanceof UploadedFile) {
                $attrs['attachment_path'] = $this->moveFile($file, 'activities');
            } elseif (is_array($action) && !empty($action['attachment_path']) && is_string($action['attachment_path'])) {
                $attrs['attachment_path'] = $action['attachment_path'];
            }

            if ($existing) {
                if ($this->canUnlockSavedDiary($request->user())) {
                    $existing->update($attrs);
                }
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->activities()->create(array_merge($attrs, ['created_by' => $userId]));
                $keep[] = $model->id;
            }
        }

        $this->keepExistingIds($enquiry->activities(), $keep, $request->user());

        $pendingDelete = $enquiry->activities()->whereNotIn('id', $keep ?: [0])->get();
        foreach ($pendingDelete as $act) {
            if (SeizeItemLock::activityHasLockedItems($act, $forensicKeys)) {
                $keep[] = $act->id;
            }
        }

        $enquiry->activities()
            ->whereNotIn('id', $keep ?: [0])
            ->delete();
    }

    private function syncLegalOpinions(Enquiry $enquiry, array $opinions, int $userId): void
    {
        $keep = [];
        foreach ($opinions as $lo) {
            if (empty($lo['role']) && empty($lo['opinion_text'])) {
                continue;
            }
            $attrs = [
                'role'         => $lo['role'] ?? 'dd_legal',
                'opinion_text' => $lo['opinion_text'] ?? '',
                'decision'     => $lo['decision'] ?? null,
            ];
            $existing = !empty($lo['id']) ? EnquiryLegalOpinion::find($lo['id']) : null;
            if ($existing && $existing->enquiry_id === $enquiry->id) {
                $existing->update($attrs);
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->legalOpinions()->create(array_merge($attrs, ['created_by' => $userId]));
                $keep[] = $model->id;
            }
        }
        $enquiry->legalOpinions()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function syncApprovals(Enquiry $enquiry, array $approvals): void
    {
        $keep = [];
        foreach ($approvals as $ap) {
            if (empty($ap['circle_incharge_id']) && empty($ap['decision'])) {
                continue;
            }
            $ciId = $ap['circle_incharge_id'] ?? null;
            if (!$ciId) {
                continue;
            }
            $attrs = [
                'circle_incharge_id' => $ciId,
                'decision'           => $ap['decision'] ?? 'agree',
                'remarks'            => $ap['remarks'] ?? null,
            ];
            $existing = !empty($ap['id']) ? EnquiryApproval::find($ap['id']) : null;
            if ($existing && $existing->enquiry_id === $enquiry->id) {
                $existing->update($attrs);
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->approvals()->create($attrs);
                $keep[] = $model->id;
            }
        }
        $enquiry->approvals()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function syncWitnesses(Enquiry $enquiry, array $witnesses, Request $request): void
    {
        $files = $request->file('witness_attachments', []);
        $pictures = $request->file('witness_pictures', []);
        $statements = $request->file('witness_statements', []);
        $keep  = [];

        foreach ($witnesses as $i => $w) {
            if (empty($w['name']) && empty($w['cnic']) && empty($w['address']) && empty($w['mailing_address'])) {
                continue;
            }
            $attrs = [
                'name'               => $w['name'] ?? null,
                'father_name'        => $w['father_name'] ?? null,
                'relation'           => $w['relation'] ?? null,
                'gender'             => $w['gender'] ?? null,
                'cnic'               => $w['cnic'] ?? null,
                'domicile_district'  => $w['domicile_district'] ?? null,
                'nationality'        => $w['nationality'] ?? null,
                'passport'           => $w['passport'] ?? null,
                'occupation'         => $w['occupation'] ?? null,
                'is_government'      => filter_var($w['is_government'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'department_name'    => $w['department_name'] ?? null,
                'designation'        => $w['designation'] ?? null,
                'scale'              => $w['scale'] ?? null,
                'contact_no'         => $w['contact_no'] ?? null,
                'whatsapp_no'        => $w['whatsapp_no'] ?? null,
                'mailing_address'    => $w['mailing_address'] ?? null,
                'permanent_address'  => $w['permanent_address'] ?? null,
                'address'            => $w['address'] ?? ($w['mailing_address'] ?? null),
            ];

            $file = $files[$i] ?? null;
            if ($file instanceof UploadedFile) {
                $attrs['attachment'] = $this->moveFile($file, 'witnesses');
            } elseif (!empty($w['attachment']) && is_string($w['attachment'])) {
                $attrs['attachment'] = $w['attachment'];
            }

            $pic = $pictures[$i] ?? null;
            if ($pic instanceof UploadedFile) {
                $attrs['picture'] = $this->moveFile($pic, 'witnesses');
            } elseif (!empty($w['picture']) && is_string($w['picture'])) {
                $attrs['picture'] = $w['picture'];
            }

            $stmt = $statements[$i] ?? null;
            if ($stmt instanceof UploadedFile) {
                $attrs['statement_attachment'] = $this->moveFile($stmt, 'witnesses');
            } elseif (!empty($w['statement_attachment']) && is_string($w['statement_attachment'])) {
                $attrs['statement_attachment'] = $w['statement_attachment'];
            }

            $existing = !empty($w['id']) ? EnquiryWitness::find($w['id']) : null;
            if (!$existing && !empty($w['cnic'])) {
                $existing = $enquiry->witnesses()->where('cnic', $w['cnic'])->first();
            }
            if (!$existing && !empty($w['name'])) {
                $existing = $enquiry->witnesses()->where('name', $w['name'])->first();
            }

            if ($existing && $existing->enquiry_id === $enquiry->id) {
                if ($this->canUnlockSavedDiary($request->user())) {
                    $existing->update($attrs);
                }
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->witnesses()->create($attrs);
                $keep[] = $model->id;
            }
        }

        $this->keepExistingIds($enquiry->witnesses(), $keep, $request->user());

        $enquiry->witnesses()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function syncNotices(Enquiry $enquiry, array $notices, Request $request): void
    {
        $keep = [];

        $hasFatherName = false;
        try {
            $hasFatherName = \Illuminate\Support\Facades\Schema::hasColumn('enquiry_notices', 'father_name');
            if (!$hasFatherName) {
                try {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE `enquiry_notices` ADD COLUMN `father_name` VARCHAR(255) NULL AFTER `receiver_name`");
                    $hasFatherName = true;
                } catch (\Throwable $e) {}
            }
        } catch (\Throwable $e) {}

        foreach ($notices as $idx => $n) {
            if (empty($n['receiver_name']) && empty($n['notice_type']) && empty($n['notice_number']) && empty($n['description'])) {
                continue;
            }

            $attrs = [
                'sequence_no'         => $n['sequence_no'] ?? ($idx + 1),
                'notice_number'       => $n['notice_number'] ?? null,
                'notice_type'         => $n['notice_type'] ?? null,
                'receiver_name'       => $n['receiver_name'] ?? null,
                'cnic'                => $n['cnic'] ?? null,
                'person_type'         => $n['person_type'] ?? null,
                'notice_via'          => !empty($n['notice_via']) ? $n['notice_via'] : null,
                'address'             => $n['address'] ?? null,
                'phone'               => $n['phone'] ?? null,
                'notice_date'         => $this->normalizeDate($n['notice_date'] ?? null),
                'description'         => $n['description'] ?? null,
                'status'              => $n['status'] ?? 'issued',
                'appearance_date'     => !empty($n['appearance_date']) ? $n['appearance_date'] : null,
                'appearance_remarks'  => $n['appearance_remarks'] ?? null,
            ];

            if ($hasFatherName) {
                $attrs['father_name'] = $n['father_name'] ?? null;
            }

            if (in_array($attrs['status'], ['served', 'non_appearance'], true) && empty($n['served_at'])) {
                $attrs['served_at'] = now();
            }

            $existing = !empty($n['id']) ? EnquiryNotice::find($n['id']) : null;
            if ($existing && $existing->enquiry_id === $enquiry->id) {
                if ($this->canUnlockSavedDiary($request->user())) {
                    $existing->update($attrs);
                    if (!$existing->verification_token) {
                        $existing->verification_token = Str::random(32);
                        $existing->save();
                    }
                }
                $keep[] = $existing->id;
            } else {
                $attrs['verification_token'] = Str::random(32);
                $model = $enquiry->notices()->create($attrs);
                $keep[] = $model->id;
            }
        }

        $this->keepExistingIds($enquiry->notices(), $keep, $request->user());

        $enquiry->notices()->whereNotIn('id', $keep ?: [0])->delete();
        $this->recomputeNoticeFlags($enquiry, $request);
    }

    private function syncAccused(Enquiry $enquiry, array $accused, Request $request): void
    {
        $cnicFiles = $request->file('accused_cnic_attachments', []);
        $passportFiles = $request->file('accused_passport_attachments', []);
        $nadraFiles = $request->file('accused_nadra_attachments', []);
        $keep = [];

        foreach ($accused as $i => $a) {
            if (empty($a['name']) && empty($a['cnic'])) {
                continue;
            }
            $attrs = [
                'name'                => $a['name'] ?? null,
                'cnic'                => $a['cnic'] ?? null,
                'father_name'         => $a['father_name'] ?? null,
                'gender'              => $a['gender'] ?? null,
                'contact_no'          => $a['contact_no'] ?? null,
                'whatsapp_no'         => $a['whatsapp_no'] ?? null,
                'email'               => $a['email'] ?? null,
                'postal_address'       => $a['postal_address'] ?? null,
                'permanent_address'   => $a['permanent_address'] ?? null,
                'religion'            => $a['religion'] ?? null,
                'district_domicile'   => $a['district_domicile'] ?? null,
                'identification_mark' => $a['identification_mark'] ?? null,
                'occupation'          => $a['occupation'] ?? null,
                'is_government'       => filter_var($a['is_government'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'department_name'     => $a['department_name'] ?? null,
                'designation'         => $a['designation'] ?? null,
                'description'         => $a['description'] ?? null,
            ];

            foreach ([
                'cnic_attachment' => [$cnicFiles[$i] ?? null, $a['cnic_attachment'] ?? null],
                'passport_attachment' => [$passportFiles[$i] ?? null, $a['passport_attachment'] ?? null],
                'nadra_verisys_attachment' => [$nadraFiles[$i] ?? null, $a['nadra_verisys_attachment'] ?? null],
            ] as $key => [$file, $existingPath]) {
                if ($file instanceof UploadedFile) {
                    $attrs[$key] = $this->moveFile($file, 'accused');
                } elseif (is_string($existingPath) && $existingPath !== '') {
                    $attrs[$key] = $existingPath;
                }
            }

            $existing = !empty($a['id']) ? EnquiryAccused::find($a['id']) : null;
            if (!$existing && !empty($a['cnic'])) {
                $existing = $enquiry->accusedPersons()->where('cnic', $a['cnic'])->first();
            }
            if (!$existing && !empty($a['name'])) {
                $existing = $enquiry->accusedPersons()->where('name', $a['name'])->first();
            }

            if ($existing && $existing->enquiry_id === $enquiry->id) {
                if ($this->canUnlockSavedDiary($request->user())) {
                    $existing->update($attrs);
                }
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->accusedPersons()->create($attrs);
                $keep[] = $model->id;
            }
        }

        $this->keepExistingIds($enquiry->accusedPersons(), $keep, $request->user());

        $enquiry->accusedPersons()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function syncEnquiryAttachments(Enquiry $enquiry, array $attachments, Request $request): void
    {
        $files = $request->file('enquiry_attachment_files', []);
        $keep = [];

        foreach ($attachments as $i => $att) {
            if (empty($att['title']) && empty($att['file_path']) && empty($files[$i])) {
                continue;
            }
            $attrs = [
                'enquiry_number'  => $att['enquiry_number'] ?? $enquiry->enquiry_number,
                'attachment_date' => $this->normalizeDate($att['attachment_date'] ?? now()),
                'title'           => $att['title'] ?? null,
                'uploaded_by'     => $request->user()->id,
            ];

            $file = $files[$i] ?? null;
            if ($file instanceof UploadedFile) {
                $attrs['file_path'] = $this->moveFile($file, 'enquiry-attachments');
                $attrs['original_name'] = $file->getClientOriginalName();
            } elseif (!empty($att['file_path']) && is_string($att['file_path'])) {
                $attrs['file_path'] = $att['file_path'];
                $attrs['original_name'] = $att['original_name'] ?? null;
            }

            $existing = !empty($att['id']) ? EnquiryAttachment::find($att['id']) : null;
            if ($existing && $existing->enquiry_id === $enquiry->id) {
                if ($this->canUnlockSavedDiary($request->user())) {
                    $existing->update($attrs);
                }
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->enquiryAttachments()->create($attrs);
                $keep[] = $model->id;
            }
        }

        $this->keepExistingIds($enquiry->enquiryAttachments(), $keep, $request->user());

        $enquiry->enquiryAttachments()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function syncRequisitions(Enquiry $enquiry, array $requisitions, Request $request): void
    {
        $keep = [];
        $canAuthorize = $request->user()->hasAnyRole(['admin', 'additional_director', 'director_general']);

        foreach ($requisitions as $r) {
            if (empty($r['type']) && empty($r['email_to']) && empty($r['body'])) {
                continue;
            }

            $attrs = [
                'type'         => $r['type'] ?? 'bank',
                'email_to'     => $r['email_to'] ?? null,
                'subject'      => $r['subject'] ?? null,
                'body'         => $r['body'] ?? null,
                'status'       => $r['status'] ?? 'draft',
                'requested_by' => $r['requested_by'] ?? $request->user()->id,
            ];

            $shouldSend = !empty($r['send_now']) || ($r['status'] ?? '') === 'send';
            if ($shouldSend && $canAuthorize) {
                $attrs['status'] = 'sent';
                $attrs['authorized_by'] = $request->user()->id;
                $attrs['authorized_at'] = now();
                $attrs['sent_at'] = now();
                try {
                    if (!empty($attrs['email_to'])) {
                        \Illuminate\Support\Facades\Mail::raw(
                            (string) ($attrs['body'] ?: 'Requisition from NCCIA CMS'),
                            function ($message) use ($attrs, $enquiry) {
                                $message->to($attrs['email_to'])
                                    ->subject($attrs['subject'] ?: ('NCCIA Requisition — ' . ($enquiry->enquiry_number ?: $enquiry->id)));
                            }
                        );
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $attrs['status'] = 'failed';
                }
            } elseif ($shouldSend && !$canAuthorize) {
                $attrs['status'] = 'draft';
            }

            $existing = !empty($r['id']) ? EnquiryRequisition::find($r['id']) : null;
            if ($existing && $existing->enquiry_id === $enquiry->id) {
                if ($this->canUnlockSavedDiary($request->user()) || $canAuthorize) {
                    $existing->update($attrs);
                }
                $keep[] = $existing->id;
            } else {
                $model = $enquiry->requisitions()->create($attrs);
                $keep[] = $model->id;
            }
        }

        $this->keepExistingIds($enquiry->requisitions(), $keep, $request->user());

        $enquiry->requisitions()->whereNotIn('id', $keep ?: [0])->delete();
    }

    private function recomputeNoticeFlags(Enquiry $enquiry, Request $request): void
    {
        $unserved       = $enquiry->notices()->whereIn('status', ['issued', 'unserved'])->count();
        $nonAppearance  = $enquiry->notices()->where('status', 'non_appearance')->count();

        $enquiry->update([
            'has_unserved_notice' => ($unserved + $nonAppearance) > 0,
            'notice_count'        => $enquiry->notices()->count(),
        ]);

        if ($nonAppearance >= 3 && !in_array($enquiry->status, ['referred_court', 'closed', 'approved', 'converted_to_case'])) {
            $enquiry->update([
                'status'         => 'referred_court',
                'recommendation' => $enquiry->recommendation ?: 'convert_to_case',
            ]);

            $this->notifyReferral($enquiry);
        }

        if ($nonAppearance > 0) {
            $this->notifyNonAppearance($enquiry, $request);
        }
    }

    private function notifyReferral(Enquiry $enquiry): void
    {
        $recipients = collect();

        if ($enquiry->enquiry_officer_id) {
            $recipients->push($enquiry->officer);
        }

        try {
            $recipients->unique('id')->each(fn ($u) => $u?->notify(new NoticeNonAppearanceNotification($enquiry, null, true)));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('NoticeNonAppearanceNotification (referral) failed: ' . $e->getMessage());
        }
    }

    private function notifyNonAppearance(Enquiry $enquiry, Request $request): void
    {
        $recipients = collect();

        if ($enquiry->enquiry_officer_id) {
            $recipients->push($enquiry->officer);
        }

        $recipients = $recipients->merge(
            \App\Models\User::role(['admin', 'circle_incharge'])->get()
        );

        try {
            $recipients->unique('id')->each(fn ($u) => $u?->notify(new NoticeNonAppearanceNotification($enquiry, null, false)));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('NoticeNonAppearanceNotification failed: ' . $e->getMessage());
        }

        // SMS the person who failed to appear (latest non-appearance notice receiver).
        try {
            $lastNotice = $enquiry->notices()->where('status', 'non_appearance')->latest()->first();
            if ($lastNotice?->phone) {
                $digits = preg_replace('/\D+/', '', $lastNotice->phone);
                if ($digits) {
                    app(SmsService::class)->sendBilingual(
                        $digits,
                        SmsTemplates::nonAppearance($enquiry, 'en'),
                        SmsTemplates::nonAppearance($enquiry, 'ur'),
                        [
                            'country_code'   => '+92',
                            'recipient_type' => 'notice_receiver',
                            'subject_type'   => 'enquiry_notice',
                            'subject_id'     => $lastNotice->id,
                            'trigger'        => 'non_appearance',
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Non-appearance SMS failed: ' . $e->getMessage());
        }
    }

    public function verifyNotice(string $token)
    {
        $notice = EnquiryNotice::with('enquiry.complaint.circle')->where('verification_token', $token)->firstOrFail();

        return view('enquiry.notice-verify', compact('notice'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Enquiry::class);

        $data = $request->validate([
            'tracking_no'             => 'nullable|string|max:255',
            'complaint_id'            => 'nullable|integer|exists:complaints,id',
            'direct_info'             => 'nullable|array',
            'enquiry_number'          => 'nullable|string|max:255',
            'reg_date'                => 'nullable|date',
            'assignment_date'         => 'nullable|date',
            'status'                  => 'required|string|in:' . self::ALLOWED_STATUSES,
            'priority'                => 'nullable|string|in:normal,high,critical',

            'enquiry_officer_id'      => 'nullable|integer|exists:users,id',
            'recommendation'          => 'nullable|string|max:50',
            'closure_reason'          => 'nullable|string|max:50',
            'transfer_department'     => 'nullable|string|max:255',
            'transfer_circle'         => 'nullable|string|max:255',
            'merge_complaint_id'      => 'nullable|string|max:255',
            'cfr_summary'             => 'nullable|string',

            // Witnesses
            'witnesses'               => 'nullable|string',
            // Notices
            'notices'                 => 'nullable|string',
            'accused'                 => 'nullable|string',
            'attachments'             => 'nullable|string',
            'requisitions'            => 'nullable|string',
            // Technical & Forensic reports
            'technical_report'        => 'nullable|string',
            'forensic_report'         => 'nullable|string',

            'activities'              => 'nullable|string',
            'legal_opinions'          => 'nullable|string',
            'approvals'               => 'nullable|string',

            // File uploads
            'technical_report_attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:20480',
            'forensic_report_attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:20480',
        ]);

        $this->decodeArrays($data);
            $request->validate($this->uploadRules());
            foreach (['activities', 'witnesses', 'accused', 'attachments'] as $field) {
                \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $data[$field] ?? [], $field);
            }

        $privileged = $request->user()->hasAnyRole(['admin', 'circle_incharge']);
        $canFillLegal = $this->canFillLegalAndApprove($request->user());
        $officerId = $privileged ? ($data['enquiry_officer_id'] ?? null) : null;

        $complaint = null;
        if (!empty($data['tracking_no'])) {
            $complaint = Complaint::where('tracking_no', $data['tracking_no'])->first();
        } elseif (!empty($data['complaint_id'])) {
            $complaint = Complaint::find($data['complaint_id']);
        }

        if ($complaint && !$request->user()->canAccessCircle($complaint->circle_id)) {
            return response()->json(['message' => 'Unauthorized. Complaint belongs to another circle.'], 403);
        }

        if (!$complaint && empty($data['direct_info']['reference_no'] ?? null)) {
            return response()->json([
                'message' => 'Select a complaint or provide direct enquiry details (reference no).',
                'errors'  => ['direct_info' => ['Reference No is required for direct enquiry.']],
            ], 422);
        }

        if (!$complaint && !empty($data['direct_info'])) {
            if ($request->user()->circle_id && !$request->user()->seesAllData()) {
                $data['direct_info']['circle_id'] = $request->user()->circle_id;
            }
        }

        if ($officerId) {
            $eo = User::find($officerId);
            $targetCircleId = $complaint ? $complaint->circle_id : ($data['direct_info']['circle_id'] ?? $request->user()->circle_id);
            if (!$eo || ($targetCircleId && (int) $eo->circle_id !== (int) $targetCircleId && !$request->user()->seesAllData())) {
                return response()->json([
                    'message' => 'Enquiry Officer must belong to the same circle as the enquiry.',
                ], 422);
            }
        }

        $enquiry = DB::transaction(function () use ($data, $complaint, $request, $officerId, $canFillLegal) {
            $enquiry = Enquiry::create([
                'complaint_id'    => $complaint?->id,
                'direct_info'     => $complaint ? null : ($data['direct_info'] ?? null),
                'enquiry_number'  => $data['enquiry_number'] ?? null,
                'enquiry_officer_id' => $officerId,
                'status'          => $data['status'] ?? 'registered',
                'reg_date'        => $data['reg_date'] ?? now()->toDateString(),
                'assignment_date' => $data['assignment_date'] ?? null,
                'recommendation'  => $data['recommendation'] ?? null,
                'closure_reason'  => $data['closure_reason'] ?? null,
                'transfer_department' => $data['transfer_department'] ?? null,
                'transfer_circle' => $data['transfer_circle'] ?? null,
                'cfr_summary'     => $data['cfr_summary'] ?? null,
                'technical_report'   => $data['technical_report'] ?? null,
                'forensic_report'    => $data['forensic_report'] ?? null,
            ]);

            if (Schema::hasColumn('enquiries', 'priority')) {
                $enquiry->priority = $data['priority'] ?? ($complaint?->priority_type);
            }

            $technicalAttachment = $this->moveFile($request->file('technical_report_attachment'), 'reports');
            $forensicAttachment  = $this->moveFile($request->file('forensic_report_attachment'), 'reports');
            if ($technicalAttachment) {
                $enquiry->technical_report_attachment = $technicalAttachment;
            }
            if ($forensicAttachment) {
                $enquiry->forensic_report_attachment = $forensicAttachment;
            }
            $enquiry->save();

            $this->syncActivities($enquiry, $data['activities'] ?? [], $request->user()->id, $request);
            if ($canFillLegal) {
                $this->syncLegalOpinions($enquiry, $data['legal_opinions'] ?? [], $request->user()->id);
                $this->syncApprovals($enquiry, $data['approvals'] ?? []);
            }
            $this->syncWitnesses($enquiry, $data['witnesses'] ?? [], $request);
            $this->syncNotices($enquiry, $data['notices'] ?? [], $request);
            $this->syncAccused($enquiry, $data['accused'] ?? [], $request);
            $this->syncEnquiryAttachments($enquiry, $data['attachments'] ?? [], $request);
            $this->syncRequisitions($enquiry, $data['requisitions'] ?? [], $request);

            return $enquiry;
        });

        app(EnquiryRecordHydrator::class)->hydrate($enquiry);

        if ($enquiry->enquiry_officer_id) {
            app(OfficerAssignmentService::class)->recordInitial(
                $enquiry,
                OfficerAssignmentService::TYPE_ENQUIRY,
                OfficerAssignmentService::ROLE_EO,
                (int) $enquiry->enquiry_officer_id,
                $request->user()->id,
            );
            try {
                $enquiry->officer?->notify(new EnquiryAssignedNotification($enquiry));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('EnquiryAssignedNotification failed: ' . $e->getMessage());
            }

            try {
                if ($enquiry->officer) {
                    app(SmsService::class)->sendToUser(
                        $enquiry->officer,
                        SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'en'),
                        SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'ur'),
                        [
                            'subject_type' => 'enquiry',
                            'subject_id'   => $enquiry->id,
                            'trigger'      => 'enquiry_assigned',
                        ]
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Officer SMS failed: ' . $e->getMessage());
            }
        }

        if ($request->expectsJson()) {
            $enquiry->load(
                'complaint',
                'officer',
                'activities.creator',
                'legalOpinions.creator',
                'approvals.circleIncharge',
                'witnesses',
                'notices',
                'accusedPersons',
                'enquiryAttachments',
                'requisitions'
            );
            $payload = $enquiry->toArray();
            $payload['accused'] = $enquiry->accusedPersons;
            $payload['attachments'] = $enquiry->enquiryAttachments;

            return response()->json(['message' => 'Enquiry registered', 'data' => $payload], 201);
        }

        return redirect()->route('enquiries.index')
            ->with('success', 'Enquiry registered — ' . ($enquiry->enquiry_number ?? ('#' . $enquiry->id)));
    }

    public function index()
    {
        try {
            $user = request()->user() ?? auth('api')->user() ?? auth()->user();
            if (!$user) {
                return response()->json(['data' => [], 'total' => 0], 200);
            }

            $query = Enquiry::visibleTo($user)->with([
                'complaint:id,tracking_no,complainant_name,cnic,circle_id',
                'officer:id,name,role,designation',
            ]);

            if ($search = trim((string) request('search'))) {
                $query->where(function ($sq) use ($search) {
                    $sq->whereHas('complaint', function ($q) use ($search) {
                        $q->where('complainant_name', 'like', "{$search}%")
                          ->orWhere('tracking_no', 'like', "{$search}%")
                          ->orWhere('cnic', 'like', "{$search}%");
                    })
                    ->orWhere('enquiry_number', 'like', "{$search}%")
                    ->orWhere('direct_info->reference_no', 'like', "{$search}%")
                    ->orWhere('direct_info->complainant_name', 'like', "{$search}%")
                    ->orWhere('id', $search);
                });
            }

            if ($status = request('status')) {
                $statuses = explode(',', $status);
                $query->whereIn('status', $statuses);
            }

            $perPage = min(100, max(1, (int) request('per_page', 15)));
            $enquiries = $query->latest('id')->paginate($perPage);

            return response()->json($enquiries);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Enquiries index error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['error' => \App\Services\ClientError::message($e), 'data' => [], 'total' => 0], 200);
        }
    }

    public function stats()
    {
        try {
            $user = request()->user() ?? auth('api')->user() ?? auth()->user();
            if (!$user) {
                return response()->json(['total' => 0, 'pending' => 0, 'progress' => 0, 'approved' => 0]);
            }

            $q = Enquiry::visibleTo($user);

            $statsRow = (clone $q)->toBase()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status IN ('registered', 'assigned', 'pending') THEN 1 ELSE 0 END) as pending")
                ->selectRaw("SUM(CASE WHEN status IN ('in_progress', 'working', 'cfr_submitted', 'referred_court', 'legal_review_dd', 'legal_review_ad', 'legal_review_dg') THEN 1 ELSE 0 END) as progress")
                ->selectRaw("SUM(CASE WHEN status IN ('approved', 'case_registered', 'closed', 'complete', 'transferred', 'converted_to_case') THEN 1 ELSE 0 END) as approved")
                ->first();

            return response()->json([
                'total'    => (int) ($statsRow->total ?? 0),
                'pending'  => (int) ($statsRow->pending ?? 0),
                'progress' => (int) ($statsRow->progress ?? 0),
                'approved' => (int) ($statsRow->approved ?? 0),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Enquiries stats error: ' . $e->getMessage());
            return response()->json(['total' => 0, 'pending' => 0, 'progress' => 0, 'approved' => 0]);
        }
    }

    public function show(Enquiry $enquiry)
    {
        $this->authorize('view', $enquiry);

        app(EnquiryRecordHydrator::class)->hydrate($enquiry);
        $enquiry->refresh();

        $enquiry->load(
            'complaint.verification.officer',
            'complaint.latestVerificationReport.creator',
            'officer',
            'caseFile',
            'activities.creator',
            'legalOpinions.creator',
            'approvals.circleIncharge',
            'witnesses',
            'notices',
            'accusedPersons',
            'enquiryAttachments',
            'requisitions.requester',
            'requisitions.authorizer'
        );

        $payload = $enquiry->toArray();

        // Clean up historical duplicate accused in DB first
        $seenAcc = [];
        $uniqueAcc = collect();
        foreach ($enquiry->accusedPersons as $item) {
            $key = strtolower(trim(($item->cnic ?: '') . '|' . ($item->name ?: '')));
            if ($key !== '|' && isset($seenAcc[$key])) {
                $item->delete();
            } else {
                if ($key !== '|') {
                    $seenAcc[$key] = true;
                }
                $uniqueAcc->push($item);
            }
        }
        $accused = $uniqueAcc;

        // Auto-fallback from verification report or complaint initial_accused if enquiry has none saved yet
        if ($accused->isEmpty() && $enquiry->complaint_id) {
            $complaint = $enquiry->complaint ?? \App\Models\Complaint::find($enquiry->complaint_id);
            $reportAccused = $complaint?->latestVerificationReport?->accused;
            if (empty($reportAccused) && $complaint) {
                $vReport = \App\Models\VerificationReport::where('complaint_id', $complaint->id)->latest('id')->first();
                $reportAccused = $vReport?->accused;
            }
            if (!empty($reportAccused) && is_array($reportAccused)) {
                $accused = collect($reportAccused)->map(function ($a) {
                    return [
                        'name'              => $a['name'] ?? '',
                        'cnic'              => $a['cnic'] ?? '',
                        'father_name'       => $a['father_name'] ?? '',
                        'gender'            => $a['gender'] ?? '',
                        'contact_no'        => $a['phone'] ?? ($a['contact_no'] ?? ''),
                        'whatsapp_no'       => $a['whatsapp_no'] ?? ($a['phone'] ?? ''),
                        'email'             => $a['email'] ?? '',
                        'postal_address'    => $a['post_address'] ?? ($a['address'] ?? ''),
                        'permanent_address' => $a['address'] ?? '',
                        'description'       => $a['description'] ?? '',
                    ];
                });
            } elseif ($complaint && !empty($complaint->initial_accused) && is_array($complaint->initial_accused)) {
                $accused = collect($complaint->initial_accused)->map(function ($a) {
                    return [
                        'name'              => $a['name'] ?? '',
                        'cnic'              => $a['cnic'] ?? '',
                        'father_name'       => $a['father_name'] ?? '',
                        'gender'            => $a['gender'] ?? '',
                        'contact_no'        => $a['contact_no'] ?? ($a['phone'] ?? ''),
                        'whatsapp_no'       => $a['whatsapp_no'] ?? ($a['contact_no'] ?? ''),
                        'email'             => $a['email'] ?? '',
                        'postal_address'    => $a['address'] ?? ($a['post_address'] ?? ''),
                        'permanent_address' => $a['address'] ?? '',
                        'description'       => $a['description'] ?? '',
                    ];
                });
            }
        }

        // Clean up historical duplicate witnesses in DB
        $seenWit = [];
        $uniqueWit = collect();
        foreach ($enquiry->witnesses as $item) {
            $key = strtolower(trim(($item->cnic ?: '') . '|' . ($item->name ?: '')));
            if ($key !== '|' && isset($seenWit[$key])) {
                $item->delete();
            } else {
                if ($key !== '|') {
                    $seenWit[$key] = true;
                }
                $uniqueWit->push($item);
            }
        }

        // Clean up historical duplicate activities (Diaries) in DB
        $seenAct = [];
        $uniqueAct = collect();
        foreach ($enquiry->activities()->orderBy('id')->get() as $item) {
            $diaryNo = trim((string) ($item->diary_no ?? ''));
            $desc = trim((string) ($item->description ?? ''));
            $date = trim((string) ($item->activity_date ?? ''));
            $key = strtolower($diaryNo ? $diaryNo : ($item->type . '|' . $date . '|' . substr($desc, 0, 40)));
            if (isset($seenAct[$key])) {
                $item->delete();
            } else {
                $seenAct[$key] = true;
                $uniqueAct->push($item);
            }
        }

        // Clean up historical duplicate notices in DB
        $seenNotice = [];
        $uniqueNotice = collect();
        foreach ($enquiry->notices()->orderBy('id')->get() as $item) {
            $key = strtolower(trim(($item->notice_number ?: '') . '|' . ($item->receiver_name ?: '') . '|' . ($item->sequence_no ?: '')));
            if ($key !== '||' && isset($seenNotice[$key])) {
                $item->delete();
            } else {
                if ($key !== '||') {
                    $seenNotice[$key] = true;
                }
                $uniqueNotice->push($item);
            }
        }

        $payload['accused'] = $accused;
        $payload['witnesses'] = $uniqueWit;
        $payload['activities'] = $uniqueAct;
        $payload['notices'] = $uniqueNotice;
        $payload['attachments'] = $enquiry->enquiryAttachments;

        return response()->json($payload);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        try {
            if ($locked = $this->denyIfForwardLocked($enquiry, $request)) {
                return $locked;
            }
            if ($stale = $this->denyIfStale($enquiry, $request)) {
                return $stale;
            }

            $this->authorize('update', $enquiry);

            $data = $request->validate([
                'tracking_no'             => 'nullable|string|max:255',
                'complaint_id'            => 'nullable|integer|exists:complaints,id',
                'enquiry_number'          => 'nullable|string|max:255',
                'enquiry_officer_id'      => 'nullable|integer|exists:users,id',
                'status'                  => 'nullable|in:' . self::ALLOWED_STATUSES,
                'priority'                => 'nullable|string|in:normal,high,critical',
                'recommendation'          => 'nullable|string|max:30',
                'closure_reason'          => 'nullable|string|max:30',
                'transfer_department'     => 'nullable|string|max:255',
                'transfer_circle'         => 'nullable|string|max:255',
                'merge_complaint_id'      => 'nullable|string|max:255',
                'reg_date'                => 'nullable|date',
                'cfr_summary'             => 'nullable|string',
                'cfr_type'                => 'nullable|string|in:CFR,SCFR',
                'cfr_date'                => 'nullable|date',
                'charge_against'          => 'nullable|string',
                'oral_evidence'           => 'nullable|string',
                'documentary_evidence'    => 'nullable|string',
                'plea'                    => 'nullable|string',
                'conclusion'              => 'nullable|string',
                'cfr_remarks'             => 'nullable|string',
                'technical_report'        => 'nullable|string',
                'forensic_report'         => 'nullable|string',

                'activities'              => 'nullable',
                'legal_opinions'          => 'nullable',
                'approvals'               => 'nullable',
                'witnesses'               => 'nullable',
                'notices'                 => 'nullable',
                'accused'                 => 'nullable',
                'attachments'             => 'nullable',
                'requisitions'            => 'nullable',

                'technical_report_attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:20480',
                'forensic_report_attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:20480',
            ]);

            $this->decodeArrays($data);
            $request->validate($this->uploadRules());
            foreach (['activities', 'witnesses', 'accused', 'attachments'] as $field) {
                \App\Services\SecureFileAccessService::validateRetainedPaths($request->user(), $data[$field] ?? [], $field);
            }

            $privileged = $request->user()->hasAnyRole(['admin', 'circle_incharge']);
            $canFillLegal = $this->canFillLegalAndApprove($request->user());
            $canEditCfrRemarks = $request->user()->hasAnyRole(['admin', 'additional_director']);

            $updateData = array_filter([
                'status'              => $data['status'] ?? null,
                'recommendation'      => $data['recommendation'] ?? null,
                'closure_reason'      => $data['closure_reason'] ?? null,
                'transfer_department' => $data['transfer_department'] ?? null,
                'transfer_circle'     => $data['transfer_circle'] ?? null,
                'enquiry_number'      => $data['enquiry_number'] ?? null,
                'reg_date'            => isset($data['reg_date']) ? $this->normalizeDate($data['reg_date']) : null,
                'cfr_summary'         => $data['cfr_summary'] ?? null,
                'cfr_type'            => $data['cfr_type'] ?? null,
                'cfr_date'            => isset($data['cfr_date']) ? $this->normalizeDate($data['cfr_date']) : null,
                'charge_against'      => $data['charge_against'] ?? null,
                'oral_evidence'       => $data['oral_evidence'] ?? null,
                'documentary_evidence'=> $data['documentary_evidence'] ?? null,
                'plea'                => $data['plea'] ?? null,
                'conclusion'          => $data['conclusion'] ?? null,
            ], fn ($v) => $v !== null);

            if (Schema::hasColumn('enquiries', 'priority') && !empty($data['priority'])) {
                $updateData['priority'] = $data['priority'];
            }

            // Always persist report text when the client sent the keys (even empty clear)
            if (array_key_exists('technical_report', $data)) {
                $updateData['technical_report'] = $data['technical_report'];
            }
            if (array_key_exists('forensic_report', $data)) {
                $updateData['forensic_report'] = $data['forensic_report'];
            }

            if ($canEditCfrRemarks && array_key_exists('cfr_remarks', $data)) {
                $updateData['cfr_remarks'] = $data['cfr_remarks'];
            }

            if ($privileged && array_key_exists('enquiry_officer_id', $data)) {
                $updateData['enquiry_officer_id'] = $data['enquiry_officer_id'] ?: null;
            }

            if (!empty($data['complaint_id'])) {
                $updateData['complaint_id'] = $data['complaint_id'];
            }

            if (!empty($data['merge_complaint_id'])) {
                $mergeComplaint = Complaint::where('tracking_no', $data['merge_complaint_id'])->first();
                $updateData['merge_complaint_id'] = $mergeComplaint?->id;
            } elseif (array_key_exists('merge_complaint_id', $data) && $data['merge_complaint_id'] === '') {
                $updateData['merge_complaint_id'] = null;
            }

            $officerChanged = $privileged && !empty($data['enquiry_officer_id'])
                && (int) $data['enquiry_officer_id'] !== (int) $enquiry->enquiry_officer_id;

            if ($officerChanged) {
                $enquiry->load('officer');
                app(OfficerAssignmentService::class)->reassign(
                    $enquiry,
                    OfficerAssignmentService::TYPE_ENQUIRY,
                    OfficerAssignmentService::ROLE_EO,
                    (int) $data['enquiry_officer_id'],
                    $request->user()->id,
                    'Officer changed via update',
                );
            }

            DB::transaction(function () use ($enquiry, $updateData, $data, $request, $canFillLegal) {
                if (!empty($updateData)) {
                    $enquiry->update($updateData);
                }

                $technicalAttachment = $this->moveFile($request->file('technical_report_attachment'), 'reports');
                if ($technicalAttachment) {
                    $enquiry->technical_report_attachment = $technicalAttachment;
                }
                $forensicAttachment = $this->moveFile($request->file('forensic_report_attachment'), 'reports');
                if ($forensicAttachment) {
                    $enquiry->forensic_report_attachment = $forensicAttachment;
                }
                if ($technicalAttachment || $forensicAttachment) {
                    $enquiry->save();
                }

                // Only sync relations when the client actually sent that key (avoid wiping on partial updates)
                if (array_key_exists('activities', $data)) {
                    $this->syncActivities($enquiry, $data['activities'] ?? [], $request->user()->id, $request);
                }
                if ($canFillLegal && array_key_exists('legal_opinions', $data)) {
                    $this->syncLegalOpinions($enquiry, $data['legal_opinions'] ?? [], $request->user()->id);
                }
                if ($canFillLegal && array_key_exists('approvals', $data)) {
                    $this->syncApprovals($enquiry, $data['approvals'] ?? []);
                }
                if (array_key_exists('witnesses', $data)) {
                    $this->syncWitnesses($enquiry, $data['witnesses'] ?? [], $request);
                }
                if (array_key_exists('notices', $data)) {
                    $this->syncNotices($enquiry, $data['notices'] ?? [], $request);
                }
                if (array_key_exists('accused', $data)) {
                    $this->syncAccused($enquiry, $data['accused'] ?? [], $request);
                }
                if (array_key_exists('attachments', $data)) {
                    $this->syncEnquiryAttachments($enquiry, $data['attachments'] ?? [], $request);
                }
                if (array_key_exists('requisitions', $data)) {
                    $this->syncRequisitions($enquiry, $data['requisitions'] ?? [], $request);
                }
            });

            if ($officerChanged) {
                try {
                    $enquiry->officer?->notify(new EnquiryAssignedNotification($enquiry));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('EnquiryAssignedNotification failed: ' . $e->getMessage());
                }

                try {
                    if ($enquiry->officer) {
                        app(SmsService::class)->sendToUser(
                            $enquiry->officer,
                            SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'en'),
                            SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'ur'),
                            [
                                'subject_type' => 'enquiry',
                                'subject_id'   => $enquiry->id,
                                'trigger'      => 'enquiry_assigned',
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Officer SMS failed: ' . $e->getMessage());
                }
            } else {
                app(OfficerAssignmentService::class)->touchWorkSnapshot(
                    $enquiry->fresh(),
                    OfficerAssignmentService::TYPE_ENQUIRY,
                );
            }

            if ($request->expectsJson()) {
                $fresh = $enquiry->fresh()->load(
                    'complaint',
                    'officer',
                    'activities.creator',
                    'legalOpinions.creator',
                    'approvals.circleIncharge',
                    'witnesses',
                    'notices',
                    'accusedPersons',
                    'enquiryAttachments',
                    'requisitions'
                );
                $payload = $fresh->toArray();
                $payload['accused'] = $fresh->accusedPersons;
                $payload['attachments'] = $fresh->enquiryAttachments;

                return response()->json([
                    'message' => 'Enquiry updated successfully',
                    'data'    => $payload,
                ]);
            }

            return redirect()->route('enquiries.index')
                ->with('success', 'Enquiry updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Enquiry update failed: ' . \App\Services\ClientError::message($e),
            ], 500);
        }
    }

    /**
     * Printable notice HTML (with QR verification code).
     */
    public function noticePrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );

        $noticeId = $request->integer('notice_id');
        $notice = $noticeId
            ? EnquiryNotice::where('enquiry_id', $enquiry->id)->findOrFail($noticeId)
            : $enquiry->notices()->latest('id')->first();

        abort_if(!$notice, 404, 'No notice found to print.');

        try {
            return response()->json([
                'html' => $print->noticePrintDocument($notice),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print summon: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    /**
     * Printable case diary HTML.
     */
    public function diaryPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );

        $activityId = $request->integer('activity_id');
        $activity = $activityId
            ? EnquiryActivity::where('enquiry_id', $enquiry->id)->findOrFail($activityId)
            : $enquiry->activities()->where('type', 'diaries')->latest('id')->first();

        abort_if(!$activity, 404, 'No diary entry found to print.');

        try {
            return response()->json([
                'html' => $print->diaryPrintDocument($enquiry, $activity),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print diary: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    /**
     * Printable Confidential Final Report (CFR) HTML.
     */
    public function cfrPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );

        try {
            return response()->json([
                'html' => $print->cfrPrintDocument($enquiry),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print CFR: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    /**
     * Printable Forensic Analysis Request HTML.
     */
    public function forensicRequestPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );

        $devices = $request->input('devices', []);
        if (is_string($devices)) {
            $devices = json_decode($devices, true) ?: [];
        }

        try {
            return response()->json([
                'html' => $print->forensicRequestPrintDocument($enquiry, is_array($devices) ? $devices : [], $request->input('analysis_scope', '')),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print forensic request: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    private function warrantPrintActivityId(Request $request): ?int
    {
        $id = (int) $request->input('activity_id', 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Fields the EO fills on raid / search / arrest activities.
     */
    private function warrantPrintDetails(Request $request): array
    {
        return [
            'subject'      => (string) $request->input('subject', ''),
            'kota'         => (string) $request->input('kota', ''),
            'against_whom' => (string) $request->input('against_whom', ''),
            'scheduled_at' => (string) $request->input('scheduled_at', ''),
            'description'  => (string) $request->input('description', ''),
        ];
    }

    /**
     * Printable Permission to Conduct a Raid HTML.
     */
    public function raidPermissionPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );
        $this->assertWarrantApproved('raid_permission', $enquiry->id);

        $teamMembers = $request->input('team_members', []);
        if (is_string($teamMembers)) {
            $teamMembers = json_decode($teamMembers, true) ?: [];
        }

        try {
            return response()->json([
                'html' => $print->raidPermissionPrintDocument($enquiry, is_array($teamMembers) ? $teamMembers : [], $this->warrantPrintDetails($request), $this->warrantPrintActivityId($request)),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print raid permission: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    /**
     * Printable Search Warrant U/S 33 of PECA-2016 HTML.
     */
    public function searchWarrantPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );
        $this->assertWarrantApproved('search_warrant', $enquiry->id);

        try {
            return response()->json([
                'html' => $print->searchWarrantPrintDocument($enquiry, $this->warrantPrintDetails($request), $this->warrantPrintActivityId($request)),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print search warrant: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    /**
     * Printable Arrest Warrant HTML.
     */
    public function arrestWarrantPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );
        $this->assertWarrantApproved('arrest_warrant', $enquiry->id);

        try {
            return response()->json([
                'html' => $print->arrestWarrantPrintDocument($enquiry, $this->warrantPrintDetails($request), $this->warrantPrintActivityId($request)),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print arrest warrant: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    /**
     * Printable Account Opening Request Proforma HTML.
     */
    public function accountOpeningPrint(Request $request, Enquiry $enquiry, PrintService $print)
    {
        abort_unless(
            Enquiry::visibleTo(request()->user())->whereKey($enquiry->id)->exists(),
            404
        );

        $params = [
            'account_no'       => $request->input('account_no', ''),
            'cnic'             => $request->input('cnic', ''),
            'account_title'    => $request->input('account_title', ''),
            'bank_name'        => $request->input('bank_name', ''),
            'fraud_amount'     => $request->input('fraud_amount', ''),
            'fraud_type'       => $request->input('fraud_type', ''),
            'reason'           => $request->input('reason', ''),
            'recovered_amount' => $request->input('recovered_amount', ''),
            'mode_of_recovery' => $request->input('mode_of_recovery', ''),
        ];

        try {
            return response()->json([
                'html' => $print->accountOpeningPrintDocument($enquiry, $params),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Could not print account opening request: ' . \App\Services\ClientError::message($e)], 500);
        }
    }

    public function destroy(Enquiry $enquiry)
    {
        $this->authorize('delete', $enquiry);

        activity()->useLog('enquiries')
            ->performedOn($enquiry)
            ->causedBy(auth()->user())
            ->withProperties(['enquiry_number' => $enquiry->enquiry_number])
            ->log('Enquiry deleted: ' . $enquiry->enquiry_number);

        $enquiry->delete();

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Enquiry deleted successfully']);
        }

        return redirect()->route('enquiries.index')
            ->with('success', 'Enquiry deleted successfully');
    }

    public function assign(Request $request, Enquiry $enquiry)
    {
        $actor = $request->user();
        abort_unless(
            Enquiry::visibleTo($actor)->whereKey($enquiry->id)->exists(),
            403,
            'Unauthorized. You cannot assign enquiries outside your circle jurisdiction.'
        );

        $data = $request->validate([
            'enquiry_officer_id' => 'required|integer|exists:users,id',
            'change_reason'      => 'nullable|string|max:500',
        ]);

        $enquiryCircleId = $enquiry->complaint?->circle_id ?? ($enquiry->direct_info['circle_id'] ?? null);
        $eo = User::findOrFail((int) $data['enquiry_officer_id']);

        if ($enquiryCircleId && (int) $eo->circle_id !== (int) $enquiryCircleId && !$actor->seesAllData()) {
            return response()->json([
                'message' => 'Enquiry Officer must belong to the same circle as the enquiry.',
            ], 422);
        }

        $enquiry->load('officer');
        app(OfficerAssignmentService::class)->reassign(
            $enquiry,
            OfficerAssignmentService::TYPE_ENQUIRY,
            OfficerAssignmentService::ROLE_EO,
            (int) $data['enquiry_officer_id'],
            $request->user()->id,
            $data['change_reason'] ?? 'Officer assigned',
        );

        $enquiry->update([
            'enquiry_officer_id' => $data['enquiry_officer_id'],
            'status'             => 'assigned',
            'assignment_date'    => now(),
        ]);

        $enquiry->officer?->notify(new EnquiryAssignedNotification($enquiry));

        if ($enquiry->officer) {
            app(SmsService::class)->sendToUser(
                $enquiry->officer,
                SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'en'),
                SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'ur'),
                [
                    'subject_type' => 'enquiry',
                    'subject_id'   => $enquiry->id,
                    'trigger'      => 'enquiry_assigned',
                ]
            );
        }

        return response()->json([
            'message' => 'Enquiry officer assigned',
            'data'    => $enquiry->fresh()->load('officer'),
        ]);
    }

    public function changeOfficer(Request $request, Enquiry $enquiry)
    {
        $actor = $request->user();
        abort_unless(
            Enquiry::visibleTo($actor)->whereKey($enquiry->id)->exists(),
            403,
            'Unauthorized. You cannot reassign enquiries outside your circle jurisdiction.'
        );

        $data = $request->validate([
            'enquiry_officer_id' => 'required|integer|exists:users,id',
            'change_reason'      => 'nullable|string|max:500',
        ]);

        $enquiryCircleId = $enquiry->complaint?->circle_id ?? ($enquiry->direct_info['circle_id'] ?? null);
        $eo = User::findOrFail((int) $data['enquiry_officer_id']);

        if ($enquiryCircleId && (int) $eo->circle_id !== (int) $enquiryCircleId && !$actor->seesAllData()) {
            return response()->json([
                'message' => 'Enquiry Officer must belong to the same circle as the enquiry.',
            ], 422);
        }

        $enquiry->load('officer');
        app(OfficerAssignmentService::class)->reassign(
            $enquiry,
            OfficerAssignmentService::TYPE_ENQUIRY,
            OfficerAssignmentService::ROLE_EO,
            (int) $data['enquiry_officer_id'],
            $request->user()->id,
            $data['change_reason'] ?? 'Officer reassigned',
        );

        $enquiry->update([
            'enquiry_officer_id' => $data['enquiry_officer_id'],
            'status'             => 'assigned',
            'assignment_date'    => now(),
        ]);

        $enquiry->officer?->notify(new EnquiryAssignedNotification($enquiry));

        if ($enquiry->officer) {
            app(SmsService::class)->sendToUser(
                $enquiry->officer,
                SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'en'),
                SmsTemplates::enquiryAssigned($enquiry, $enquiry->officer, 'ur'),
                [
                    'subject_type' => 'enquiry',
                    'subject_id'   => $enquiry->id,
                    'trigger'      => 'enquiry_assigned',
                ]
            );
        }

        return response()->json([
            'message' => 'Enquiry officer reassigned. Previous officer work saved in history.',
            'data'    => $enquiry->fresh()->load('officer'),
        ]);
    }

    public function submitCfr(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'cfr_summary'    => 'required|string',
            'recommendation' => 'nullable|string|in:closure,transfer,convert_to_case,fir,irrelevant,lack_of_documents',
            'cfr_type'       => 'nullable|string|in:CFR,SCFR',
            'cfr_date'       => 'nullable|date',
            'charge_against' => 'nullable|string',
            'oral_evidence'  => 'nullable|string',
            'documentary_evidence' => 'nullable|string',
            'plea'           => 'nullable|string',
            'conclusion'     => 'nullable|string',
            'cfr_remarks'    => 'nullable|string',
        ]);

        $rec = $data['recommendation'] ?? $enquiry->recommendation ?: 'convert_to_case';
        if ($rec === 'fir') {
            $rec = 'convert_to_case';
        }
        if ($rec === 'irrelevant' || $rec === 'lack_of_documents') {
            $rec = 'closure';
        }

        $update = [
            'status'         => 'cfr_submitted',
            'recommendation' => $rec,
            'cfr_summary'    => $data['cfr_summary'],
            'submitted_at'   => now(),
        ];
        foreach (['cfr_type', 'charge_against', 'oral_evidence', 'documentary_evidence', 'plea', 'conclusion'] as $f) {
            if (!empty($data[$f])) {
                $update[$f] = $data[$f];
            }
        }
        if (!empty($data['cfr_date'])) {
            $update['cfr_date'] = $this->normalizeDate($data['cfr_date']);
        }
        if ($request->user()->hasAnyRole(['admin', 'additional_director']) && array_key_exists('cfr_remarks', $data)) {
            $update['cfr_remarks'] = $data['cfr_remarks'];
        }

        $enquiry->update($update);

        EnquiryActivity::create([
            'enquiry_id'    => $enquiry->id,
            'type'          => 'cfr',
            'description'   => $data['cfr_summary'],
            'activity_date' => now(),
            'created_by'    => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'CFR submitted successfully',
            'data'    => $enquiry->fresh()->load('activities'),
        ]);
    }

    public function approve(Request $request, Enquiry $enquiry)
    {
        abort_unless($this->canFillLegalAndApprove($request->user()), 403, 'Only Circle Incharge / Legal can approve this enquiry.');

        $data = $request->validate([
            'decision'           => 'required|string|in:agree,review,disagree',
            'remarks'            => 'nullable|string|max:2000',
            'recommendation'     => 'nullable|string|in:closure,transfer,merge,convert_to_case',
            'closure_reason'     => 'nullable|string|in:non_pursuance,irrelevant,invalid,lack_of_evidence,compromise',
            'merge_complaint_id' => 'nullable|integer|exists:complaints,id',
            'transfer_department'=> 'nullable|string|max:255',
            'transfer_circle'    => 'nullable|string|max:255',
        ]);

        $approval = EnquiryApproval::create([
            'enquiry_id'         => $enquiry->id,
            'circle_incharge_id' => $request->user()->id,
            'decision'           => $data['decision'],
            'remarks'            => $data['remarks'] ?? null,
        ]);

        $recommendation = $data['recommendation'] ?? $enquiry->recommendation;

        $updateData = [
            'recommendation'     => $recommendation,
            'closure_reason'     => $data['closure_reason'] ?? null,
            'transfer_department'=> $data['transfer_department'] ?? null,
            'transfer_circle'    => $data['transfer_circle'] ?? null,
            'merge_complaint_id' => $data['merge_complaint_id'] ?? null,
        ];

        $complaint = $enquiry->complaint;

        try {
            DB::transaction(function () use ($data, $enquiry, $complaint, $recommendation, &$updateData, $approval) {
                if ($data['decision'] === 'agree') {
                    $updateData['status'] = 'approved';
                    $updateData['approved_at'] = now();

                    switch ($recommendation) {
                        case 'closure':
                            $complaint?->update([
                                'status'         => 'closed',
                                'final_status'   => 'closed',
                                'closure_reason' => $data['closure_reason'] ?? null,
                            ]);
                            break;

                        case 'merge':
                            $complaint?->update([
                                'status'         => 'merged',
                                'final_status'   => 'merged',
                                'merged_with_id' => $data['merge_complaint_id'] ?? null,
                            ]);
                            break;

                        case 'transfer':
                            $complaint?->update([
                                'status'                => 'transferred',
                                'final_status'          => 'transferred',
                                'transfer_to_department'=> $data['transfer_department'] ?? null,
                                'transfer_to_circle_id' => null,
                            ]);
                            break;

                        case 'convert_to_case':
                            $directInfo = $enquiry->direct_info;
                            $circleCode = $complaint?->circle?->code
                                ?: ($directInfo['circle_code'] ?? null)
                                ?: ($directInfo['circle_id'] ? Circle::find($directInfo['circle_id'])?->code : null);
                            $gen = app(FirNumberGenerator::class);
                            $caseFile = CaseFile::create([
                                'enquiry_id'   => $enquiry->id,
                                'direct_info'  => $complaint ? null : $directInfo,
                                'fir_no'       => $gen->generate($circleCode),
                                'status'       => 'registered',
                            ]);
                            $updateData['case_file_id'] = $caseFile->id;
                            $complaint?->update([
                                'status'       => 'case_registered',
                                'final_status' => 'case_registered',
                            ]);
                            break;
                    }
                } else {
                    $updateData['status'] = 'in_progress';
                }

                $enquiry->update($updateData);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Enquiry approve failed: ' . \App\Services\ClientError::message($e),
            ], 500);
        }

        return response()->json([
            'message' => 'Enquiry ' . ($data['decision'] === 'agree' ? 'approved' : 'sent for review'),
            'data'    => [
                'enquiry'  => $enquiry->fresh()->load('approvals', 'caseFile'),
                'approval' => $approval,
            ],
        ]);
    }

    public function registerCase(Request $request, Enquiry $enquiry)
    {
        $this->authorize('registerCase', $enquiry);

        $request->merge([
            'investigation_officer_id' => $request->input('investigation_officer_id') ?: null,
        ]);

        $data = $request->validate([
            'investigation_officer_id' => 'nullable|integer|exists:users,id',
            'remarks'                  => 'nullable|string|max:2000',
        ]);

        try {
            $caseFile = DB::transaction(function () use ($enquiry, $data, $request) {
                $directInfo = $enquiry->direct_info;
                $complaint  = $enquiry->complaint()->with('circle')->first();
                $circleCode = $complaint?->circle?->code
                    ?: ($directInfo['circle_code'] ?? null)
                    ?: ($directInfo['circle_id'] ? Circle::find($directInfo['circle_id'])?->code : null);
                $gen        = app(FirNumberGenerator::class);

                $caseAttrs = [
                    'enquiry_id'               => $enquiry->id,
                    'direct_info'              => $complaint ? null : $directInfo,
                    'fir_no'                   => $gen->generate($circleCode),
                    'investigation_officer_id' => $data['investigation_officer_id'] ?? null,
                    'status'                   => 'registered',
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('cases', 'priority')) {
                    $caseAttrs['priority'] = $enquiry->priority ?: ($complaint?->priority_type ?: 'normal');
                }
                $caseFile = CaseFile::create($caseAttrs);

                $enquiry->update([
                    'status'         => 'converted_to_case',
                    'recommendation' => 'convert_to_case',
                    'case_file_id'   => $caseFile->id,
                    'approved_at'    => $enquiry->approved_at ?? now(),
                ]);

                if ($complaint) {
                    $complaint->update([
                        'status'       => 'case_registered',
                        'final_status' => 'case_registered',
                    ]);
                }

                EnquiryApproval::create([
                    'enquiry_id'         => $enquiry->id,
                    'circle_incharge_id' => $request->user()->id,
                    'decision'           => 'agree',
                    'remarks'            => $data['remarks'] ?? 'Case registered via Register Case action',
                ]);

                return $caseFile;
            });

            if ($caseFile->investigation_officer_id) {
                $caseFile->investigationOfficer?->notify(new CaseAssignedNotification($caseFile));

                if ($caseFile->investigationOfficer) {
                    app(SmsService::class)->sendToUser(
                        $caseFile->investigationOfficer,
                        SmsTemplates::caseAssigned($caseFile, $caseFile->investigationOfficer, 'en'),
                        SmsTemplates::caseAssigned($caseFile, $caseFile->investigationOfficer, 'ur'),
                        [
                            'subject_type' => 'case_file',
                            'subject_id'   => $caseFile->id,
                            'trigger'      => 'case_assigned',
                        ]
                    );
                }
            }

            return response()->json([
                'message' => 'Case/FIR registered — ' . $caseFile->fir_no,
                'data'    => [
                    'enquiry'   => $enquiry->fresh()->load('caseFile', 'complaint'),
                    'case_file' => $caseFile->load('enquiry.complaint', 'investigationOfficer'),
                ],
            ], 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Case registration failed: ' . \App\Services\ClientError::message($e),
            ], 500);
        }
    }
}
