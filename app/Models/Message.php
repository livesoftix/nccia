<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'enquiry_id',
        'case_file_id',
        'case_number',
        'message',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read'  => 'boolean',
            'read_at'  => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }

    public function scopeBetween(Builder $query, int $userA, int $userB): Builder
    {
        return $query->whereNull('enquiry_id')->whereNull('case_file_id')->where(function ($q) use ($userA, $userB) {
            $q->where(function ($sq) use ($userA, $userB) {
                $sq->where('sender_id', $userA)->where('receiver_id', $userB);
            })->orWhere(function ($sq) use ($userA, $userB) {
                $sq->where('sender_id', $userB)->where('receiver_id', $userA);
            });
        });
    }

    public function scopeForEnquiry(Builder $query, int $enquiryId): Builder
    {
        return $query->where('enquiry_id', $enquiryId);
    }

    public function scopeForCase(Builder $query, int $caseId): Builder
    {
        return $query->where('case_file_id', $caseId);
    }
}
