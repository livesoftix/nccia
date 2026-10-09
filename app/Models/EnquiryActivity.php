<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryActivity extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['attachment_path'];

    protected $fillable = [
        'enquiry_id',
        'type',
        'diary_no',
        'description',
        'meta',
        'activity_date',
        'attachment_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date:Y-m-d',
            'meta' => 'array',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
