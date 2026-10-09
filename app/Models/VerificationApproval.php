<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationApproval extends Model
{
    protected $fillable = [
        'verification_id',
        'circle_incharge_id',
        'decision',
        'recommendation',
        'closure_reason',
        'merge_complaint_id',
        'transfer_department',
        'transfer_circle_id',
        'remarks',
    ];

    public function verification(): BelongsTo
    {
        return $this->belongsTo(Verification::class);
    }

    public function circleIncharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'circle_incharge_id');
    }

    public function mergeComplaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class, 'merge_complaint_id');
    }

    public function transferCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'transfer_circle_id');
    }
}
