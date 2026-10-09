<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtHearing extends Model
{
    protected $fillable = [
        'court_case_id',
        'hearing_date',
        'type',
        'notes',
        'next_hearing_date',
    ];

    protected function casts(): array
    {
        return [
            'hearing_date'      => 'date:Y-m-d',
            'next_hearing_date' => 'date:Y-m-d',
        ];
    }

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'court_case_id');
    }
}
