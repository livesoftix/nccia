<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseApproval extends Model
{
    protected $fillable = [
        'case_id',
        'circle_incharge_id',
        'decision',
        'remarks',
    ];

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function circleIncharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'circle_incharge_id');
    }
}
