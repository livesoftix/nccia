<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CourtCase extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $table = 'court_cases';

    protected $fillable = [
        'case_id',
        'court_name',
        'judge_name',
        'filing_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'filing_date' => 'date:Y-m-d',
        ];
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function hearings(): HasMany
    {
        return $this->hasMany(CourtHearing::class, 'court_case_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CourtReport::class, 'court_case_id');
    }

    public function verdicts(): HasMany
    {
        return $this->hasMany(CourtVerdict::class, 'court_case_id');
    }

    /**
     * Data isolation: users only see court cases linked to cases they can see.
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

        return $query->whereIn('case_id', CaseFile::visibleTo($user)->select('id'));
    }
}
