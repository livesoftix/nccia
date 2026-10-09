<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtReport extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['file_path'];

    protected $fillable = [
        'court_case_id',
        'report_type',
        'file_path',
        'submitted_by',
    ];

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'court_case_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
