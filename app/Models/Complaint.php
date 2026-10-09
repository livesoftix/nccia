<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Complaint extends Model
{
    use LogsActivity;
    use \App\Models\Concerns\LocksWhenForwarded;

    /**
     * Once a complaint leaves intake (assigned to verification or beyond) the
     * operator who registered it keeps read access only.
     */
    protected array $forwardLockStatuses = [
        'assigned', 'approved', 'complete', 'merged', 'transferred', 'closed',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'tracking_no',
        'source',
        'complainant_name',
        'father_name',
        'cnic',
        'contact_no',
        'whatsapp_no',
        'gender',
        'email',
        'contact_country_code',
        'nationality',
        'passport_no',
        'address',
        'post_address',
        'district',
        'profession',
        'report_date',
        'reporting_time',
        'diary_no',
        'received_via',
        'received_from',
        'cmu',
        'priority_type',
        'offence_type',
        'crime_mediums',
        'amount_involved',
        'bank_name_sender',
        'bank_name_receiver',
        'account_no_sender',
        'account_no_receiver',
        'transaction_date',
        'occurrence_date',
        'laws',
        'description',
        'platforms',
        'platform_profile_page',
        'platform_username',
        'platform_email_involved',
        'platform_mobile_involved',
        'evidence',
        'initial_accused',
        'operator_name',
        'operator_designation',
        'entry_time',
        'scrutiny_result',
        'operator_remarks',
        'registration_message',
        'registration_notify_via',
        'registration_notified_at',
        'status',
        'final_status',
        'closure_reason',
        'merged_with_id',
        'transfer_to_department',
        'transfer_to_circle_id',
        'enquiry_id',
        'user_id',
        'operator_id',
        'circle_id',
        'attachment',
        'cnic_front',
        'cnic_back',
        'passport_attachment',
        'picture',
        'slip_generated',
        'slip_generated_at',
        'slip_number',
    ];

    protected function casts(): array
    {
        return [
            'laws' => 'array',
            'evidence' => 'array',
            'platforms' => 'array',
            'crime_mediums' => 'array',
            'initial_accused' => 'array',
            'amount_involved' => 'decimal:2',
            'report_date' => 'date:Y-m-d',
            'transaction_date' => 'date:Y-m-d',
            'occurrence_date' => 'date:Y-m-d',
            'entry_time' => 'datetime',
            'reporting_time' => 'datetime',
            'slip_generated_at' => 'datetime',
            'registration_notified_at' => 'datetime',
            'slip_generated' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(Verification::class);
    }

    public function latestVerificationReport(): HasOne
    {
        return $this->hasOne(VerificationReport::class)->latestOfMany();
    }

    public function caseFiles(): HasManyThrough
    {
        return $this->hasManyThrough(CaseFile::class, Enquiry::class, 'complaint_id', 'enquiry_id', 'id', 'id');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function mergedWith(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'merged_with_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    public function transferToCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'transfer_to_circle_id');
    }

    /**
     * Data isolation: a user may only see complaints they own/are assigned to.
     * Supervisory roles (admin etc.) see everything.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->seesAllData()) {
            return $query;
        }

        // Zonal / Regional Head (e.g. Director Punjab / Lahore Central Directorate overseeing Punjab Zone)
        if ($user->isZonalHead()) {
            $effectiveZoneId = $user->zone_id ?: $user->circle?->zone_id;
            return $query->whereHas('circle', fn ($cq) => $cq->where('zone_id', $effectiveZoneId));
        }

        // Regional Circle Incharge, Front Desk Officer, and Station Staff: strictly limited to their own circle
        if ($user->hasRole('circle_incharge') || $user->hasAnyRole(['operator', 'front_desk_officer', 'moharrar', 'reader_branch', 'ad_administration'])) {
            $circleId = $user->circle_id ?: 0;
            return $query->where('circle_id', $circleId);
        }

        // Scale-safe: EXISTS subqueries — recognize all officer roles and designations
        $userRole = $user->role ?? '';
        $userDesig = strtolower($user->designation ?? '');
        $hasVo = $user->hasRole('verification_officer') || $userRole === 'verification_officer';
        $hasEo = $user->hasRole('enquiry_officer') || $userRole === 'enquiry_officer' || $userRole === 'inspector' || $user->hasRole('inspector') || str_contains($userDesig, 'inspector') || str_contains($userDesig, 'enquiry');
        $hasIo = $user->hasRole('investigation_officer') || $userRole === 'investigation_officer' || str_contains($userDesig, 'investigation');

        if (!$hasVo && !$hasEo && !$hasIo) {
            if ($user->circle_id) {
                return $query->where('circle_id', $user->circle_id);
            }
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($user, $hasVo, $hasEo, $hasIo) {
            if ($hasVo) {
                $q->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('verifications')
                        ->whereColumn('verifications.complaint_id', 'complaints.id')
                        ->where('verifications.verification_officer_id', $user->id);
                });
            }
            if ($hasEo) {
                $q->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('enquiries')
                        ->whereColumn('enquiries.complaint_id', 'complaints.id')
                        ->where('enquiries.enquiry_officer_id', $user->id);
                });
            }
            if ($hasIo) {
                $q->orWhereExists(function ($sub) use ($user) {
                    $sub->selectRaw('1')
                        ->from('enquiries')
                        ->join('cases', 'cases.enquiry_id', '=', 'enquiries.id')
                        ->whereColumn('enquiries.complaint_id', 'complaints.id')
                        ->where('cases.investigation_officer_id', $user->id);
                });
            }
        })->when($user->circle_id, fn($q) => $q->where('circle_id', $user->circle_id));
    }

    /**
     * Lifecycle progress percentage (aligned with realerp_nccia.sql):
     * incomplete(10) → complete(25) → verification assigned(40)
     * → verification submitted/approved(60) → enquiry registered(70)
     * → enquiry in_progress/cfr_submitted(80) → enquiry approved(85)
     * → enquiry closed/converted_to_case(95) → case registered(90)
     * → case closed(100). Terminal: final_status set → 100.
     */
    public function progressPercent(): int
    {
        $terminal = [
            'closed', 'merged', 'transferred', 'invalid', 'irrelevant',
            'closed_conviction', 'closed_acquittal', 'closed_discharge', 'closed_dismissed',
        ];

        if ($this->final_status && in_array($this->final_status, $terminal, true)) {
            return 100;
        }

        if (in_array($this->status, ['invalid', 'irrelevant'], true)) {
            return 100;
        }

        $case = $this->relationLoaded('caseFiles')
            ? $this->caseFiles->first()
            : $this->caseFiles()->first();

        if ($case) {
            if (in_array($case->status, ['closed', 'challan_submitted'], true) || str_starts_with((string) $case->status, 'closed')) {
                return 100;
            }

            return 90;
        }

        $enquiry = $this->enquiry;
        if ($enquiry) {
            if (in_array($enquiry->status, ['closed', 'converted_to_case', 'referred_court'], true)) {
                return 95;
            }
            if ($enquiry->status === 'approved') {
                return 85;
            }
            if (in_array($enquiry->status, ['cfr_submitted', 'in_progress', 'assigned'], true)) {
                return 80;
            }

            return 70;
        }

        $verification = $this->verification;
        if ($verification) {
            if ($verification->status === 'approved') {
                return 65;
            }

            return in_array($verification->status, ['submitted'], true) ? 60 : 40;
        }

        return $this->status === 'complete' ? 25 : 10;
    }

    public function progressStage(): string
    {
        $p = $this->progressPercent();
        if ($p >= 100) return 'Resolved';
        if ($p >= 95)  return 'Enquiry Closed';
        if ($p >= 90)  return 'Case Registered';
        if ($p >= 85)  return 'Enquiry Approved';
        if ($p >= 80)  return 'Enquiry In Progress';
        if ($p >= 70)  return 'Enquiry Registered';
        if ($p >= 65)  return 'Verification Approved';
        if ($p >= 60)  return 'Verification Submitted';
        if ($p >= 40)  return 'Under Verification';
        if ($p >= 25)  return 'Scrutiny Complete';
        return 'Incomplete';
    }

    /**
     * Workflow steps for progress UI: Complaint → Verification → Enquiry → Case → Court.
     *
     * @return array{steps: list<array{key:string,label:string,state:string}>, current: string, percent: int}
     */
    public function workflowProgress(): array
    {
        $case = $this->relationLoaded('caseFiles')
            ? $this->caseFiles->first()
            : $this->caseFiles()->first();
        $enquiry = $this->enquiry;
        $verification = $this->verification;
        $terminal = [
            'closed', 'merged', 'transferred', 'invalid', 'irrelevant',
            'closed_conviction', 'closed_acquittal', 'closed_discharge', 'closed_dismissed',
        ];
        $done = $this->final_status && in_array($this->final_status, $terminal, true);

        $courtReached = $done && $case;

        $steps = [
            [
                'key'   => 'complaint',
                'label' => 'Complaint',
                'state' => $this->status === 'complete' || $verification || $enquiry || $case || $done
                    ? 'done'
                    : 'current',
            ],
            [
                'key'   => 'verification',
                'label' => 'Verification',
                'state' => $enquiry || $case || ($verification && $verification->status === 'approved') || $done
                    ? 'done'
                    : ($verification ? 'current' : 'todo'),
            ],
            [
                'key'   => 'enquiry',
                'label' => 'Enquiry',
                'state' => $case || ($enquiry && in_array($enquiry->status, ['approved', 'converted_to_case', 'closed', 'referred_court'], true)) || ($done && !$verification)
                    ? 'done'
                    : ($enquiry ? 'current' : 'todo'),
            ],
            [
                'key'   => 'case',
                'label' => 'Case',
                'state' => ($case && in_array($case->status, ['closed', 'challan_submitted'], true)) || $courtReached
                    ? 'done'
                    : ($case ? 'current' : 'todo'),
            ],
            [
                'key'   => 'court',
                'label' => 'Court',
                'state' => $courtReached ? 'done' : 'todo',
            ],
        ];

        $current = collect($steps)->firstWhere('state', 'current')['key']
            ?? (collect($steps)->last(fn ($s) => $s['state'] === 'done')['key'] ?? 'complaint');

        return [
            'steps'   => $steps,
            'current' => $current,
            'percent' => $this->progressPercent(),
            'stage'   => $this->progressStage(),
        ];
    }
}
