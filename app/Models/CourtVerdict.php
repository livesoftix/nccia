<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtVerdict extends Model
{
    protected $fillable = [
        'court_case_id',
        'verdict',
        'verdict_date',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'verdict_date' => 'date:Y-m-d',
        ];
    }

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'court_case_id');
    }
}
