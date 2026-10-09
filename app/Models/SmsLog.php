<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $table = 'sms_logs';

    protected $fillable = [
        'phone',
        'country_code',
        'message',
        'lang',
        'trigger',
        'recipient_type',
        'subject_type',
        'subject_id',
        'status',
        'response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function getDisplayPhoneAttribute(): string
    {
        return ($this->country_code ?: '+92') . $this->phone;
    }
}