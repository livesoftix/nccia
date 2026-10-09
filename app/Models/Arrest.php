<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Arrest extends Model
{
    protected $fillable = [
        'case_id',
        'accused_name',
        'cnic',
        'arrest_date',
        'remand_details',
    ];

    protected function casts(): array
    {
        return [
            'arrest_date' => 'date:Y-m-d',
        ];
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }
}
