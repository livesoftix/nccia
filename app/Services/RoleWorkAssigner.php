<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\CaseAssignedNotification;
use App\Notifications\EnquiryAssignedNotification;
use App\Notifications\VerificationAssignedNotification;
use App\Services\EnquiryNumberGenerator;
use App\Services\SmsService;
use App\Services\SmsTemplates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Distributes unassigned workflow items to officers by role + circle (least-loaded).
 * Flowchart: Complaint → Verification → Enquiry → Case.
 */
class RoleWorkAssigner
{
    public function __construct(
        private readonly EnquiryNumberGenerator $enquiryNumbers,
    ) {
    }

    /**
     * @return array<string,int>
     */
    public function assignAll(?int $limitPerRole = 25): array
    {
        return [
            'operators_linked'       => $this->linkComplaintsToOperators($limitPerRole),
            'verifications_assigned' => $this->assignVerifications($limitPerRole),
            'enquiries_created'      => $this->createEnquiriesFromApproved($limitPerRole),
            'enquiries_assigned'     => $this->assignEnquiries($limitPerRole),
            'enquiry_numbers'        => $this->fillEnquiryNumbers($limitPerRole),
            'cases_created'          => $this->createCasesFromEnquiries($limitPerRole),
            'cases_assigned'         => $this->assignCases($limitPerRole),
            'fir_numbers'            => $this->fillFirNumbers($limitPerRole),
        ];
    }

    public function linkComplaintsToOperators(int $limit = 25): int
    {
        $operators = User::role('operator')->get();
        if ($operators->isEmpty()) {
            return 0;
        }

        $count = 0;
        Complaint::query()
            ->whereNull('operator_id')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Complaint $c) use ($operators, &$count) {
                $op = $this->pickOfficer($operators, $c->circle_id);
                $c->update([
                    'operator_id' => $op->id,
                    'user_id'     => $c->user_id ?: $op->id,
                ]);
                $count++;
            });

        return $count;
    }

    public function assignVerifications(int $limit = 25): int
    {
        $officers = User::role('verification_officer')->get();
        $assigners = User::role('circle_incharge')->get()->merge(User::role('admin')->get());
        if ($officers->isEmpty()) {
            return 0;
        }

        $count = 0;

        // Complete complaints with no verification yet
        Complaint::query()
            ->where('status', 'complete')
            ->whereNotNull('tracking_no')
            ->whereDoesntHave('verification')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Complaint $c) use ($officers, $assigners, &$count) {
                $vo = $this->pickOfficer($officers, $c->circle_id);
                $by = $this->pickOfficer($assigners->isNotEmpty() ? $assigners : $officers, $c->circle_id);

                $v = Verification::create([
                    'complaint_id'             => $c->id,
                    'verification_officer_id'  => $vo->id,
                    'assigned_by'              => $by->id,
                    'priority_type'            => in_array($c->priority_type, ['normal', 'high', 'critical'], true)
                        ? $c->priority_type
                        : 'normal',
                    'status'                   => 'assigned',
                    'assigned_at'              => now(),
                ]);
                $vo->notify(new VerificationAssignedNotification($v));
                app(SmsService::class)->sendToUser(
                    $vo,
                    SmsTemplates::verificationAssigned($v, $vo, 'en'),
                    SmsTemplates::verificationAssigned($v, $vo, 'ur'),
                    ['subject_type' => 'verification', 'subject_id' => $v->id, 'trigger' => 'verification_assigned']
                );
                $count++;
            });

        // Orphan verifications without officer
        Verification::query()
            ->whereNull('verification_officer_id')
            ->orWhere(function ($q) {
                $q->where('status', 'pending_assignment');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Verification $v) use ($officers, &$count) {
                if ($v->verification_officer_id && $v->status !== 'pending_assignment') {
                    return;
                }
                $vo = $this->pickOfficer($officers, $v->complaint?->circle_id);
                $v->update([
                    'verification_officer_id' => $vo->id,
                    'status'                  => 'assigned',
                    'assigned_at'             => $v->assigned_at ?: now(),
                ]);
                $vo->notify(new VerificationAssignedNotification($v->fresh()));
                app(SmsService::class)->sendToUser(
                    $vo,
                    SmsTemplates::verificationAssigned($v->fresh(), $vo, 'en'),
                    SmsTemplates::verificationAssigned($v->fresh(), $vo, 'ur'),
                    ['subject_type' => 'verification', 'subject_id' => $v->id, 'trigger' => 'verification_assigned']
                );
                $count++;
            });

        return $count;
    }

    public function createEnquiriesFromApproved(int $limit = 25): int
    {
        $count = 0;

        Verification::query()
            ->where('status', 'approved')
            ->where('recommendation', 'enquiry_registration')
            ->whereHas('complaint', function ($q) {
                $q->whereDoesntHave('enquiries');
            })
            ->with('complaint.circle')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Verification $v) use (&$count) {
                $complaint = $v->complaint;
                if (!$complaint) {
                    return;
                }

                DB::transaction(function () use ($complaint, &$count) {
                    $enquiry = Enquiry::create([
                        'complaint_id'   => $complaint->id,
                        'enquiry_number' => null,
                        'status'         => 'registered',
                        'reg_date'       => now()->toDateString(),
                    ]);
                    $complaint->update([
                        'status'       => 'enquiry_registered',
                        'final_status' => 'enquiry_registered',
                        'enquiry_id'   => $enquiry->id,
                    ]);
                    $count++;
                });
            });

        return $count;
    }

    public function assignEnquiries(int $limit = 25): int
    {
        $officers = User::role('enquiry_officer')->get();
        if ($officers->isEmpty()) {
            return 0;
        }

        $count = 0;
        Enquiry::query()
            ->whereNull('enquiry_officer_id')
            ->whereIn('status', ['registered', 'assigned'])
            ->with('complaint')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Enquiry $e) use ($officers, &$count) {
                $eo = $this->pickOfficer($officers, $e->complaint?->circle_id);
                $e->update([
                    'enquiry_officer_id' => $eo->id,
                    'status'             => 'assigned',
                    'assignment_date'    => now()->toDateString(),
                ]);
                $eo->notify(new EnquiryAssignedNotification($e->fresh()));
                app(SmsService::class)->sendToUser(
                    $eo,
                    SmsTemplates::enquiryAssigned($e->fresh(), $eo, 'en'),
                    SmsTemplates::enquiryAssigned($e->fresh(), $eo, 'ur'),
                    ['subject_type' => 'enquiry', 'subject_id' => $e->id, 'trigger' => 'enquiry_assigned']
                );
                $count++;
            });

        return $count;
    }

    public function fillEnquiryNumbers(int $limit = 25): int
    {
        $count = 0;
        Enquiry::query()
            ->where(function ($q) {
                $q->whereNull('enquiry_number')->orWhere('enquiry_number', '');
            })
            ->with('complaint.circle')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Enquiry $e) use (&$count) {
                $code = $e->complaint?->circle?->code;
                $e->update([
                    'enquiry_number' => $this->enquiryNumbers->generate($code),
                ]);
                $count++;
            });

        return $count;
    }

    public function createCasesFromEnquiries(int $limit = 25): int
    {
        $count = 0;
        Enquiry::query()
            ->whereIn('status', ['approved', 'converted_to_case'])
            ->where(function ($q) {
                $q->where('recommendation', 'convert_to_case')
                    ->orWhere('status', 'converted_to_case');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (Enquiry $e) use (&$count) {
                if (CaseFile::where('enquiry_id', $e->id)->exists()) {
                    return;
                }
                CaseFile::create([
                    'enquiry_id'   => $e->id,
                    'complaint_id' => $e->complaint_id,
                    'status'       => 'registered',
                    'fir_no'       => null,
                ]);
                $count++;
            });

        return $count;
    }

    public function assignCases(int $limit = 25): int
    {
        $officers = User::role('investigation_officer')->get();
        if ($officers->isEmpty()) {
            return 0;
        }

        $count = 0;
        CaseFile::query()
            ->whereNull('investigation_officer_id')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (CaseFile $c) use ($officers, &$count) {
                $circleId = optional(Enquiry::find($c->enquiry_id)?->complaint)->circle_id
                    ?? optional(Complaint::find($c->complaint_id))->circle_id;
                $io = $this->pickOfficer($officers, $circleId);
                $c->update([
                    'investigation_officer_id' => $io->id,
                    'status'                   => 'assigned',
                ]);
                if (method_exists($io, 'notify')) {
                    try {
                        $io->notify(new CaseAssignedNotification($c->fresh()));
                    } catch (\Throwable $e) {
                        // notification optional
                    }
                }
                app(SmsService::class)->sendToUser(
                    $io,
                    SmsTemplates::caseAssigned($c->fresh(), $io, 'en'),
                    SmsTemplates::caseAssigned($c->fresh(), $io, 'ur'),
                    ['subject_type' => 'case_file', 'subject_id' => $c->id, 'trigger' => 'case_assigned']
                );
                $count++;
            });

        return $count;
    }

    public function fillFirNumbers(int $limit = 25): int
    {
        $count = 0;
        CaseFile::query()
            ->where(function ($q) {
                $q->whereNull('fir_no')->orWhere('fir_no', '');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (CaseFile $c) use (&$count) {
                $c->update([
                    'fir_no' => 'FIR-' . now()->format('y') . '-' . str_pad((string) $c->id, 5, '0', STR_PAD_LEFT),
                ]);
                $count++;
            });

        return $count;
    }

    private function pickOfficer(Collection $officers, ?int $circleId): User
    {
        $pool = $circleId
            ? $officers->where('circle_id', $circleId)->values()
            : collect();

        if ($pool->isEmpty()) {
            $pool = $officers->values();
        }

        // Least loaded: fewest open assignments among VO/EO/IO heuristics
        return $pool->sortBy(function (User $u) {
            $v = Verification::where('verification_officer_id', $u->id)
                ->whereIn('status', ['assigned', 'in_progress', 'sent_back'])->count();
            $e = Enquiry::where('enquiry_officer_id', $u->id)
                ->whereIn('status', ['assigned', 'in_progress'])->count();
            $c = CaseFile::where('investigation_officer_id', $u->id)
                ->whereIn('status', ['assigned', 'in_progress', 'registered'])->count();

            return $v + $e + $c;
        })->first();
    }
}
