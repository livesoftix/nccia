<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestigationOfficer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'badge_no',
        'designation',
        'circle',
        'zone',
        'contact_no',
        'contact_country_code',
        'email',
        'address',
        'date_of_joining',
        'status',
        'remarks',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
        ];
    }
}
