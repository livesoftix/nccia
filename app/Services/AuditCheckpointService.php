<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditCheckpointService
{
    public function create(): array
    {
        return DB::transaction(function () {
            $head = DB::table('audit_ledger_head')->where('id', 1)->lockForUpdate()->first();
            if (!$head || !AuditLedgerService::verify()['ok']) {
                throw new \RuntimeException('Audit verification failed.');
            }
            $checkpoint = ['schema_version' => 1, 'issued_at' => now()->utc()->toIso8601String(),
                'last_id' => (int) $head->last_id, 'entries' => (int) $head->entries, 'head_hash' => $head->hash];
            return $checkpoint + ['signature' => $this->signature($checkpoint)];
        });
    }

    public function verify(array $checkpoint): bool
    {
        $keys = array_keys($checkpoint);
        sort($keys);
        if ($keys !== ['entries', 'head_hash', 'issued_at', 'last_id', 'schema_version', 'signature']
            || $checkpoint['schema_version'] !== 1 || !is_int($checkpoint['last_id']) || !is_int($checkpoint['entries'])
            || $checkpoint['last_id'] < 0 || $checkpoint['entries'] < 0 || !is_string($checkpoint['issued_at'])
            || !preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00\z/', $checkpoint['issued_at'])
            || !is_string($checkpoint['head_hash']) || !preg_match('/\A[a-f0-9]{64}\z/', $checkpoint['head_hash'])
            || !is_string($checkpoint['signature']) || !preg_match('/\A[a-f0-9]{64}\z/', $checkpoint['signature'])) {
            return false;
        }
        if (!hash_equals($this->signature($checkpoint), $checkpoint['signature'])) {
            return false;
        }
        return DB::transaction(function () use ($checkpoint) {
            DB::table('audit_ledger_head')->where('id', 1)->lockForUpdate()->first();
            if (!AuditLedgerService::verify()['ok']) {
                return false;
            }
            if ($checkpoint['last_id'] === 0) {
                return $checkpoint['entries'] === 0 && $checkpoint['head_hash'] === str_repeat('0', 64);
            }
            $hash = DB::table('audit_ledger')->where('id', $checkpoint['last_id'])->value('hash');
            return is_string($hash) && hash_equals($checkpoint['head_hash'], $hash)
                && DB::table('audit_ledger')->where('id', '<=', $checkpoint['last_id'])->count() === $checkpoint['entries'];
        });
    }

    private function signature(array $checkpoint): string
    {
        $key = (string) (config('security.audit_hmac_key') ?: config('app.key'));
        if ($key === '') {
            throw new \RuntimeException('Audit checkpoint key is unavailable.');
        }
        $data = [$checkpoint['schema_version'], $checkpoint['issued_at'], $checkpoint['last_id'],
            $checkpoint['entries'], $checkpoint['head_hash']];
        return hash_hmac('sha256', 'nccia-audit-checkpoint-v1:'.json_encode($data, JSON_THROW_ON_ERROR), $key);
    }
}
