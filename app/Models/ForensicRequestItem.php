<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForensicRequestItem extends Model
{
    protected $fillable = [
        'forensic_request_id', 'item_type', 'make_model', 'imei', 'imei2',
        'serial_no', 'storage_capacity', 'condition', 'seized_from', 'quantity', 'description',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ForensicRequest::class, 'forensic_request_id');
    }
}
