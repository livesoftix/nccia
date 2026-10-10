<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintPdfImport extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['stored_path'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_IMPORTED = 'imported';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'circle_id',
        'batch_id',
        'original_filename',
        'stored_path',
        'file_hash',
        'file_size',
        'inquiry_ref',
        'status',
        'extracted_data',
        'field_results',
        'review_reasons',
        'circle_hint',
        'mean_confidence',
        'layout',
        'import_result',
        'error_message',
        'complaint_id',
        'verification_report_id',
        'page_count',
        'pages_done',
        'attempts',
        'used_ocr',
        'processed_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data'  => 'array',
            'field_results'   => 'array',
            'review_reasons'  => 'array',
            'import_result'   => 'array',
            'used_ocr'        => 'boolean',
            'mean_confidence' => 'float',
            'processed_at'    => 'datetime',
            'reviewed_at'     => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(ComplaintPdfImportPage::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function verificationReport(): BelongsTo
    {
        return $this->belongsTo(VerificationReport::class);
    }

    /**
     * Data isolation. Circle-scoped imports (circle_id set at upload) are visible only
     * inside that circle's authority: HQ sees all, a zonal head sees circles in their
     * zone, everyone else only their own circle. Legacy imports without a circle keep
     * the previous uploader/complaint-based rule.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->seesAllData()) {
            return $query;
        }

        $zoneId = $user->isZonalHead() ? ($user->zone_id ?: $user->circle?->zone_id) : null;

        return $query->where(function ($q) use ($user, $zoneId) {
            $q->where(function ($scoped) use ($user, $zoneId) {
                $scoped->whereNotNull('circle_id');
                if ($zoneId) {
                    $scoped->whereIn('circle_id', Circle::where('zone_id', $zoneId)->select('id'));
                } else {
                    $scoped->where('circle_id', $user->circle_id ?: 0);
                }
            })->orWhere(function ($legacy) use ($user) {
                $legacy->whereNull('circle_id')->where(function ($l) use ($user) {
                    $l->where('user_id', $user->id)
                      ->orWhereIn('complaint_id', Complaint::visibleTo($user)->select('id'));
                    if ($user->circle_id) {
                        $l->orWhereIn('user_id', User::where('circle_id', $user->circle_id)->select('id'));
                    }
                });
            });
        });
    }
}
