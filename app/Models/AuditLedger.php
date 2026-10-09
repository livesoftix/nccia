<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger entry. Never update or delete these in application code.
 */
class AuditLedger extends Model
{
    public const UPDATED_AT = null; // append-only: no updated_at
    protected $table = 'audit_ledger';

    protected $fillable = [
        'event', 'user_id', 'user_name', 'subject_type', 'subject_id', 'description',
        'properties', 'ip', 'user_agent', 'data_hash', 'prev_hash', 'hash', 'created_at', 'hash_version',
    ];

    protected $casts = [
        'properties_ciphertext' => 'encrypted:array',
        'hash_version' => 'integer',
        'created_at' => 'datetime',
    ];

    protected $hidden = ['properties_ciphertext'];

    public function getPropertiesAttribute($value): ?array
    {
        if ($this->getRawOriginal('properties_ciphertext') !== null || isset($this->attributes['properties_ciphertext'])) {
            return $this->properties_ciphertext;
        }
        return $value === null ? null : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    public function setPropertiesAttribute($value): void
    {
        $this->setAttribute('properties_ciphertext', $value);
        $this->attributes['properties'] = null;
    }

    /** Guard against accidental mutation/deletion of ledger history. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new \RuntimeException('Audit ledger entries are immutable.'));
        static::deleting(fn () => throw new \RuntimeException('Audit ledger entries cannot be deleted.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
