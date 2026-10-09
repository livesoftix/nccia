<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryRequisition extends Model
{
    protected $fillable = [
        'enquiry_id',
        'type',
        'email_to',
        'subject',
        'body',
        'status',
        'requested_by',
        'authorized_by',
        'authorized_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
