<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proclamation extends Model
{
    protected $fillable = [
        'case_file_id', 'fir_no', 'accused_name', 'accused_father_name', 'accused_address',
        'offence', 'court_name', 'proclaimed_on', 'appear_by_date', 'publication_place',
        'status', 'created_by', 'approved_by',
    ];

    protected $casts = [
        'proclaimed_on'  => 'date',
        'appear_by_date' => 'date',
    ];

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }

    public function attachments()
    {
        return $this->hasMany(PropertyAttachment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
