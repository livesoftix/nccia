<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryAccused extends Model
{
    use \App\Models\Concerns\HasSecureFileUrls;
    private const SECURE_FILE_FIELDS = ['cnic_attachment', 'passport_attachment', 'nadra_verisys_attachment'];

    protected $table = 'enquiry_accused';

    protected $fillable = [
        'enquiry_id',
        'name',
        'cnic',
        'father_name',
        'gender',
        'contact_no',
        'whatsapp_no',
        'email',
        'postal_address',
        'permanent_address',
        'religion',
        'district_domicile',
        'identification_mark',
        'occupation',
        'is_government',
        'department_name',
        'designation',
        'description',
        'cnic_attachment',
        'passport_attachment',
        'nadra_verisys_attachment',
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
