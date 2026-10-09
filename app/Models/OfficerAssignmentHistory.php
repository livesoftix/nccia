<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerAssignmentHistory extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'officer_role',
        'officer_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'unassigned_by',
        'change_reason',
        'work_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'   => 'datetime',
            'unassigned_at' => 'datetime',
            'work_snapshot' => 'array',
        ];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function unassignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unassigned_by');
    }
}
