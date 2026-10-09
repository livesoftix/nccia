<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryAttachment extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['file_path'];

    protected $fillable = [
        'enquiry_id',
        'enquiry_number',
        'attachment_date',
        'title',
        'file_path',
        'original_name',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'attachment_date' => 'date:Y-m-d',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
