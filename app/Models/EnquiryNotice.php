<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EnquiryNotice extends Model
{
    protected $fillable = [
        'enquiry_id',
        'sequence_no',
        'notice_number',
        'notice_type',
        'receiver_name',
        'father_name',
        'person_type',
        'cnic',
        'notice_via',
        'address',
        'phone',
        'notice_date',
        'description',
        'status',
        'served_at',
        'appeared_at',
        'appearance_date',
        'appearance_remarks',
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'notice_date'      => 'date:Y-m-d',
            'served_at'        => 'datetime',
            'appeared_at'      => 'datetime',
            'appearance_date'  => 'datetime',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function scopeEnsureToken($query)
    {
        return $query->whereNull('verification_token')->each(function ($notice) {
            $notice->verification_token = Str::random(32);
            $notice->save();
        });
    }
}