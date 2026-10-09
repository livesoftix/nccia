<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintPdfImport extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['stored_path'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'batch_id',
        'original_filename',
        'stored_path',
        'inquiry_ref',
        'status',
        'extracted_data',
        'import_result',
        'error_message',
        'complaint_id',
        'verification_report_id',
        'page_count',
        'used_ocr',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data'  => 'array',
            'import_result'   => 'array',
            'used_ocr'        => 'boolean',
            'processed_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function verificationReport(): BelongsTo
    {
        return $this->belongsTo(VerificationReport::class);
    }
}
