<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DsrReport extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED_CI = 'submitted_to_ci';

    public const STATUS_CI_APPROVED = 'ci_approved';

    public const STATUS_FORWARDED_HQ = 'forwarded_to_hq';

    public const STATUS_HQ_ACK = 'hq_acknowledged';

    public const STATUS_SENT_BACK = 'sent_back';

    protected $fillable = [
        'circle_id',
        'report_date',
        'unit_name',
        'status',
        'highlights',
        'payload',
        'notes',
        'compiled_by',
        'submitted_to_ci_at',
        'ci_reviewed_by',
        'ci_reviewed_at',
        'ci_remarks',
        'forwarded_to_hq_at',
        'forwarded_by',
        'hq_acknowledged_at',
        'hq_acknowledged_by',
        'send_back_remarks',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date:Y-m-d',
            'highlights' => 'array',
            'payload' => 'array',
            'submitted_to_ci_at' => 'datetime',
            'ci_reviewed_at' => 'datetime',
            'forwarded_to_hq_at' => 'datetime',
            'hq_acknowledged_at' => 'datetime',
        ];
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function compiler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'compiled_by');
    }

    public function ciReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ci_reviewed_by');
    }

    public function forwarder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'forwarded_by');
    }

    public function hqAcknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hq_acknowledged_by');
    }

    public function scopeVisibleTo($query, ?User $user)
    {
        if (!$user) {
            return $query->whereRaw('0=1');
        }

        if ($user->hasAnyRole(['admin', 'director_general'])) {
            return $query;
        }

        if ($user->hasRole('circle_incharge') && $user->circle_id) {
            return $query->where('circle_id', $user->circle_id);
        }

        if ($user->hasRole('ad_administration') && $user->circle_id) {
            return $query->where('circle_id', $user->circle_id);
        }

        return $query->whereRaw('0=1');
    }
}
