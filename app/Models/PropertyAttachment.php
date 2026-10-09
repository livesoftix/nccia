<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyAttachment extends Model
{
    protected $fillable = [
        'proclamation_id', 'case_file_id', 'property_type', 'property_description',
        'location', 'estimated_value', 'attachment_date', 'order_no', 'status', 'created_by',
    ];

    protected $casts = [
        'attachment_date' => 'date',
        'estimated_value' => 'decimal:2',
    ];

    public function proclamation()
    {
        return $this->belongsTo(Proclamation::class);
    }

    public function caseFile()
    {
        return $this->belongsTo(CaseFile::class, 'case_file_id');
    }
}
