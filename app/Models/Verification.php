<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Verification extends Model
{
    use LogsActivity;
    use \App\Models\Concerns\LocksWhenForwarded;

    /**
     * Once a verification is submitted / disposed, the verification officer
     * keeps read access only. 'sent_back' deliberately re-opens it for rework.
     */
    protected array $forwardLockStatuses = [
        'submitted', 'approved', 'enquiry_registered', 'merged', 'transferred', 'closed',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'complaint_id',
        'direct_info',
        'accused',
        'verification_officer_id',
        'assigned_by',
        'status',
        'recommendation',
        'report_text',
        'closure_reason',
        'merge_complaint_id',
        'transfer_department',
        'transfer_circle_id',
        'priority_type',
        'assigned_at',
        'submitted_at',
        'approved_at',
        'completed_at',
        'appeared_at',
        'complainant_message',
        'message_via',
        'whatsapp_sent_at',
        'sent_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'          => 'datetime',
            'submitted_at'         => 'datetime',
            'approved_at'          => 'datetime',
            'completed_at'         => 'datetime',
            'appeared_at'          => 'datetime',
            'whatsapp_sent_at'     => 'datetime',
            'direct_info'          => 'array',
            'accused'              => 'array',
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
        return $this->belongsTo(User::class, 'verification_officer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function transferCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'transfer_circle_id');
    }

    public function mergeComplaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'merge_complaint_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(VerificationApproval::class);
    }

    /**
     * Data isolation: users only see verifications they are assigned to
     * (or that belong to complaints they can see). Supervisory roles see all.
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

        if ($user->hasRole('verification_officer')) {
            return $query->where('verification_officer_id', $user->id)
                ->whereIn('complaint_id', Complaint::visibleTo($user)->select('id'));
        }

        // Subquery (not pluck) — keeps memory flat at multi-million scale
        return $query->whereIn('complaint_id', Complaint::visibleTo($user)->select('id'));
    }
}
