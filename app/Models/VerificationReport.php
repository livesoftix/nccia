<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationReport extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['accused', 'evidence', 'transactions'];

    protected $fillable = [
        'complaint_id',
        'tracking_no',
        'registration_at',
        'assignment_date',
        'verification_date',
        'victim_name',
        'victim_father_name',
        'victim_occupation',
        'victim_gender',
        'victim_cnic',
        'victim_country_code',
        'victim_phone',
        'victim_email',
        'crime_category',
        'crime_description',
        'city',
        'accused_known',
        'accused',
        'recommendation',
        'closure_reason',
        'recommendation_short',
        'recommendation_full',
        'comments',
        'evidence',
        'transactions',
        'signature',
        'inquiry_no',
        'case_no',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'registration_at'   => 'datetime',
            'assignment_date'   => 'datetime',
            'verification_date' => 'datetime',
            'accused_known'     => 'boolean',
            'accused'           => 'array',
            'evidence'          => 'array',
            'transactions'      => 'array',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
      * Data isolation: a user only sees reports they created, except a
      * circle_incharge, who sees reports for complaints in their own circle.
      * Supervisory roles see all.
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

        return $query->whereIn('complaint_id', Complaint::visibleTo($user)->select('id'));
    }
}
