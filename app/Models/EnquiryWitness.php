<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryWitness extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['attachment', 'picture', 'statement_attachment'];

    protected $fillable = [
        'enquiry_id',
        'name',
        'father_name',
        'relation',
        'gender',
        'cnic',
        'domicile_district',
        'nationality',
        'passport',
        'occupation',
        'is_government',
        'department_name',
        'designation',
        'scale',
        'contact_no',
        'whatsapp_no',
        'mailing_address',
        'permanent_address',
        'address',
        'attachment',
        'picture',
        'statement_attachment',
    ];

    protected function casts(): array
    {
        return [
            'is_government' => 'boolean',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }
}
