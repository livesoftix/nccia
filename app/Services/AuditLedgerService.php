<?php

namespace App\Services;

use App\Models\AuditLedger;
use Illuminate\Support\Facades\DB;

/**
 * Records tamper-evident, hash-chained audit entries and verifies the chain.
 */
class AuditLedgerService
{
    private const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Append one entry to the ledger. Returns the created entry (or null on failure;
     * auditing must never break the request it is recording).
     */
    public static function record(string $event, array $context = []): ?AuditLedger
    {
        try {
            $request = request();
            $user = $context['user'] ?? (function_exists('auth') ? auth()->user() : null);

            $payload = [
                'event'        => $event,
                'user_id'      => $context['user_id'] ?? $user?->id,
                'user_name'    => $context['user_name'] ?? $user?->name,
                'subject_type' => $context['subject_type'] ?? null,
                'subject_id'   => $context['subject_id'] ?? null,
                'description'  => $context['description'] ?? null,
                'properties'   => $context['properties'] ?? null,
                'ip'           => $context['ip'] ?? $request?->ip(),
                'user_agent'   => substr((string) ($context['user_agent'] ?? $request?->userAgent()), 0, 255),
                'created_at'   => now(),
                'hash_version' => 2,
            ];

            // Serialize under a short lock so prev_hash is consistent under concurrency.
            return DB::transaction(function () use ($payload) {
                // Lock a permanent singleton, including the first append to an empty ledger.
                $head = DB::table('audit_ledger_head')->where('id', 1)->lockForUpdate()->first();
                self::assertHead($head);
                $prev = $head->hash;
                $dataHash = self::payloadHash($payload);
                $hash = self::chainHash($prev, $dataHash, $payload['created_at']->toIso8601String(), 2);

                $entry = AuditLedger::create($payload + [
                    'data_hash' => $dataHash,
                    'prev_hash' => $prev,
                    'hash'      => $hash,
                ]);
                $next = ['last_id' => $entry->id, 'entries' => (int) $head->entries + 1, 'hash' => $entry->hash];
                DB::table('audit_ledger_head')->where('id', 1)->update($next + ['mac' => self::headMac($next)]);
                return $entry;
            }, 3);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::critical('Audit ledger write failed', ['exception_type' => get_class($e)]);
            if (request()->attributes->get('audit_write_required')) {
                request()->attributes->set('audit_write_failed', true);
                throw new \App\Exceptions\AuditUnavailable('Audit storage is unavailable.', 0, $e);
            }
            return null;
        }
    }

    public static function assertWritable(): void
    {
        try {
            self::assertHead(DB::table('audit_ledger_head')->where('id', 1)->first());
        } catch (\Throwable $e) {
            request()->attributes->set('audit_write_failed', true);
            throw new \App\Exceptions\AuditUnavailable('Audit storage is unavailable.', 0, $e);
        }
    }

    private static function assertHead(?object $head): void
    {
        if (!$head || !hash_equals($head->mac, self::headMac((array) $head))) {
            throw new \RuntimeException('Audit ledger head is missing or altered.');
        }
        $last = DB::table('audit_ledger')->orderByDesc('id')->first(['id', 'hash']);
        if ((int) $head->last_id !== (int) ($last->id ?? 0)
            || !hash_equals($head->hash, $last->hash ?? self::GENESIS)
            || (!$last && (int) $head->entries !== 0)) {
            throw new \RuntimeException('Audit ledger tail is missing or altered.');
        }
    }

    /**
     * Walk the whole chain and confirm nothing was altered or removed.
     * Returns ['ok' => bool, 'checked' => int, 'broken_at' => ?id].
     */
    public static function verify(): array
    {
        return DB::transaction(function () {
            $head = DB::table('audit_ledger_head')->where('id', 1)->lockForUpdate()->first();
            $prev = self::GENESIS;
            $checked = 0;
            $lastId = 0;
            try {
                foreach (AuditLedger::query()->orderBy('id')->cursor() as $entry) {
                    $checked++;
                    $payload = $entry->getAttributes();
                    $payload['properties'] = $entry->properties;
                    $payload['created_at'] = $entry->created_at;
                    $version = (int) ($entry->hash_version ?? 1);
                    if (!in_array($version, [1, 2], true)) {
                        return ['ok' => false, 'checked' => $checked, 'broken_at' => $entry->id];
                    }
                    if ($version === 2 && $entry->getRawOriginal('properties') !== null) {
                        return ['ok' => false, 'checked' => $checked, 'broken_at' => $entry->id];
                    }
                    $dataHash = self::payloadHash($payload);
                    $expected = self::chainHash($prev, $dataHash, $entry->created_at->toIso8601String(), $version);
                    if (!hash_equals($prev, $entry->prev_hash) || !hash_equals($dataHash, $entry->data_hash) || !hash_equals($expected, $entry->hash)) {
                        return ['ok' => false, 'checked' => $checked, 'broken_at' => $entry->id];
                    }
                    $prev = $entry->hash;
                    $lastId = $entry->id;
                }
                $ok = $head && (int) $head->entries === $checked && (int) $head->last_id === $lastId
                    && hash_equals($head->hash, $prev) && hash_equals($head->mac, self::headMac((array) $head));
                return ['ok' => (bool) $ok, 'checked' => $checked, 'broken_at' => $ok ? null : $lastId];
            } catch (\Throwable) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $lastId ?: null];
            }
        });
    }

    public static function headMac(array $head): string
    {
        return hash_hmac('sha256', self::json([(int) $head['last_id'], (int) $head['entries'], $head['hash']]), self::key());
    }

    private static function payloadHash(array $payload): string
    {
        $timestamp = $payload['created_at']->toIso8601String();
        if ((int) ($payload['hash_version'] ?? 1) === 1) {
            return hash('sha256', self::json([
                $payload['event'], $payload['user_id'], $payload['subject_type'],
                $payload['subject_id'], $payload['properties'], $timestamp,
            ]));
        }
        return hash_hmac('sha256', self::json([
            $payload['event'], $payload['user_id'], $payload['user_name'], $payload['subject_type'],
            $payload['subject_id'], $payload['description'], $payload['properties'], $payload['ip'], $payload['user_agent'], $timestamp,
        ]), self::key());
    }

    private static function chainHash(string $prev, string $data, string $timestamp, int $version): string
    {
        return $version === 1 ? hash('sha256', $prev . $data . $timestamp)
            : hash_hmac('sha256', $prev . $data . $timestamp, self::key());
    }

    private static function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function key(): string
    {
        $key = (string) (config('security.audit_hmac_key') ?: config('app.key'));
        if ($key === '') {
            throw new \RuntimeException('Audit encryption key is unavailable.');
        }
        return $key;
    }
}
