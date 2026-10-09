<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryApproval extends Model
{
    protected $fillable = [
        'enquiry_id',
        'circle_incharge_id',
        'decision',
        'remarks',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function circleIncharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'circle_incharge_id');
    }
}
