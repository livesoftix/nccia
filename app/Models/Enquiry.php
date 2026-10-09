<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Enquiry extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['technical_report_attachment', 'forensic_report_attachment'];

    use LogsActivity;
    use \App\Models\Concerns\LocksWhenForwarded;

    /**
     * Once the CFR is submitted / the enquiry moves into the legal-review chain
     * or is disposed, the enquiry officer keeps read access only.
     */
    protected array $forwardLockStatuses = [
        'cfr_submitted',
        'legal_review_dd',
        'legal_review_ad',
        'legal_review_dg',
        'approved',
        'closed',
        'converted_to_case',
        'transferred',
        'referred_court',
        'merged',
        'challan_submitted',
        'complete',
        'case_registered',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected static function booted(): void
    {
        static::saving(function (Enquiry $enquiry) {
            if (!array_key_exists('priority', $enquiry->getAttributes())) {
                return;
            }
            static $hasPriority = null;
            $hasPriority ??= Schema::hasColumn('enquiries', 'priority');
            if (!$hasPriority) {
                unset($enquiry->priority);
            }
        });
    }

    protected $fillable = [
        'complaint_id',
        'direct_info',
        'enquiry_number',
        'enquiry_officer_id',
        'status',
        'priority',
        'recommendation',
        'closure_reason',
        'transfer_department',
        'transfer_circle',
        'merge_complaint_id',
        'cfr_summary',
        'cfr_type',
        'cfr_date',
        'charge_against',
        'oral_evidence',
        'documentary_evidence',
        'plea',
        'conclusion',
        'cfr_remarks',
        'reg_date',
        'assignment_date',
        'submitted_at',
        'approved_at',
        'case_file_id',
        // Witness fields
        'witness_name',
        'witness_cnic',
        'witness_nationality',
        'witness_passport',
        'witness_address',
        'witness_attachment',
        // Notice fields
        'notice_number',
        'notice_type',
        'notice_receiver_name',
        'notice_date',
        'notice_via',
        'notice_person_type',
        'notice_description',
        'notice_address',
        'notice_phone',
        'notice_served',
        'notice_served_at',
        'notice_count',
        // Technical & Forensic Reports
        'technical_report',
        'forensic_report',
        'technical_report_attachment',
        'forensic_report_attachment',
        // Notice flags
        'has_unserved_notice',
        // Slip generation
        'slip_generated',
        'slip_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'reg_date'              => 'datetime',
            'assignment_date'       => 'datetime',
            'submitted_at'          => 'datetime',
            'approved_at'           => 'datetime',
            'cfr_date'              => 'date:Y-m-d',
            'notice_date'           => 'date:Y-m-d',
            'notice_served_at'      => 'datetime',
            'notice_served'         => 'boolean',
            'has_unserved_notice'   => 'boolean',
            'slip_generated'        => 'boolean',
            'slip_generated_at'     => 'datetime',
            'notice_count'          => 'integer',
            'direct_info'           => 'array',
        ];
    }

    /**
     * Display reference: complaint tracking no, else direct reference.
     */
    public function reference(): ?string
    {
        return $this->complaint?->tracking_no
            ?: ($this->direct_info['reference_no'] ?? null);
    }

    /**
     * Display complainant name: from complaint, else direct snapshot.
     */
    public function complainantName(): ?string
    {
        return $this->complaint?->complainant_name
            ?: ($this->direct_info['complainant_name'] ?? null);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enquiry_officer_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EnquiryActivity::class);
    }

    public function legalOpinions(): HasMany
    {
        return $this->hasMany(EnquiryLegalOpinion::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(EnquiryApproval::class);
    }

    public function witnesses(): HasMany
    {
        return $this->hasMany(EnquiryWitness::class);
    }

    public function notices(): HasMany
    {
        return $this->hasMany(EnquiryNotice::class);
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }

    public function hasRegisteredCase(): bool
    {
        if ($this->case_file_id) {
            return true;
        }

        return CaseFile::where('enquiry_id', $this->id)->exists();
    }

    public function accusedPersons(): HasMany
    {
        return $this->hasMany(EnquiryAccused::class);
    }

    public function enquiryAttachments(): HasMany
    {
        return $this->hasMany(EnquiryAttachment::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(EnquiryRequisition::class);
    }

    /**
     * Number of notices on this enquiry where the person failed to appear.
     */
    public function nonAppearanceCount(): int
    {
        return $this->notices()->where('status', 'non_appearance')->count();
    }

    /**
     * Number of notices currently unserved / pending appearance.
     */
    public function unservedNoticeCount(): int
    {
        return $this->notices()->whereIn('status', ['issued', 'unserved'])->count();
    }

    /**
     * True when a person failed to appear (gold star shown on the enquiry).
     */
    public function hasNonAppearance(): bool
    {
        return (bool) $this->nonAppearanceCount();
    }

    /**
     * After three non-appearance notices the matter is referred to court
     * and a case file is registered against the person.
     */
    public function shouldReferToCourt(): bool
    {
        return $this->nonAppearanceCount() >= 3;
    }

    /**
     * Backward-compatible shim: an enquiry is "locked for the officer" once it
     * has been forwarded past the enquiry stage. Status list now lives in the
     * LocksWhenForwarded trait ($forwardLockStatuses) as the single source.
     */
    public function isLockedForOfficer(): bool
    {
        return $this->isForwarded();
    }

    /**
     * Data isolation:
     * - Supervisors see all
     * - Circle Incharge sees their circle
     * - Enquiry / Investigation Officer sees only records assigned to them
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        $user = $user ?? auth('api')->user() ?? auth()->user() ?? request()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->seesAllData()) {
            return $query;
        }

        // Zonal / Regional Head (e.g. Director Punjab / Lahore Central Directorate)
        if ($user->isZonalHead()) {
            $effectiveZoneId = $user->zone_id ?: $user->circle?->zone_id;
            return $query->where(function ($q) use ($effectiveZoneId) {
                $q->whereHas('complaint.circle', fn ($cq) => $cq->where('zone_id', $effectiveZoneId))
                  ->orWhere(function ($dq) use ($effectiveZoneId) {
                      $dq->whereNull('complaint_id')
                         ->whereHas('officer.circle', fn ($oq) => $oq->where('zone_id', $effectiveZoneId));
                  });
            });
        }

        $userRole = $user->role ?? '';
        $isCi = $user->hasRole('circle_incharge') || $userRole === 'circle_incharge';
        $isEo = $user->hasAnyRole(['enquiry_officer', 'investigation_officer', 'inspector', 'sub_inspector', 'officer'])
            || in_array($userRole, ['enquiry_officer', 'investigation_officer', 'inspector', 'sub_inspector', 'officer'], true);

        $isPureVo = ($user->hasRole('verification_officer') || $userRole === 'verification_officer')
            && !$isCi && !$isEo && !$user->hasAnyRole([
                'admin', 'operator', 'front_desk_officer', 'moharrar', 'reader_branch', 'ad_legal', 'dd_legal',
                'additional_director', 'director_general',
            ]);

        if ($isPureVo) {
            return $query->whereRaw('1 = 0');
        }

        $isStaff = $isCi || $user->hasAnyRole(['director_general', 'additional_director', 'dd_legal', 'ad_legal', 'admin', 'moharrar', 'reader_branch', 'ad_administration']);

        if ($isStaff) {
            $circleId = $user->circle_id ?: 0;
            return $query->where(function ($q) use ($circleId) {
                $q->whereHas('complaint', function ($sub) use ($circleId) {
                    $sub->where('circle_id', $circleId);
                })->orWhere(function ($dq) use ($circleId) {
                    $dq->whereNull('complaint_id')
                       ->where(function ($jsonQ) use ($circleId) {
                           $jsonQ->where('direct_info->circle_id', $circleId)
                                 ->orWhere('direct_info->circle_id', (string) $circleId);
                       });
                });
            });
        }

        if ($isEo) {
            $circleId = $user->circle_id ?: 0;
            return $query->where(function ($q) use ($user) {
                $q->where('enquiry_officer_id', $user->id)
                  ->orWhereHas('caseFile', function ($c) use ($user) {
                      $c->where('investigation_officer_id', $user->id);
                  });
            })->where(function ($cq) use ($circleId) {
                $cq->whereHas('complaint', fn ($sub) => $sub->where('circle_id', $circleId))
                   ->orWhere('direct_info->circle_id', $circleId)
                   ->orWhere('direct_info->circle_id', (string) $circleId);
            });
        }

        $circleId = $user->circle_id ?: 0;
        return $query->where('enquiry_officer_id', $user->id)
            ->where(function ($cq) use ($circleId) {
                $cq->whereHas('complaint', fn ($sub) => $sub->where('circle_id', $circleId))
                   ->orWhere('direct_info->circle_id', $circleId)
                   ->orWhere('direct_info->circle_id', (string) $circleId);
            });
    }
}
