<?php

namespace Tests\Feature;

use App\Services\AuditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_details_are_encrypted_and_all_metadata_is_authenticated(): void
    {
        $entry = AuditLedgerService::record('security.test', ['user_name' => 'Example', 'description' => 'Verified action',
            'properties' => ['identity' => 'fictional-sensitive-value'], 'ip' => '127.0.0.1']);
        $this->assertNotNull($entry);
        $raw = DB::table('audit_ledger')->where('id', $entry->id)->first();
        $this->assertNull($raw->properties);
        $this->assertStringNotContainsString('fictional-sensitive-value', $raw->properties_ciphertext);
        $this->assertSame(['identity' => 'fictional-sensitive-value'], $entry->fresh()->properties);
        $this->assertTrue(AuditLedgerService::verify()['ok']);
        DB::table('audit_ledger')->where('id', $entry->id)->update(['ip' => '192.0.2.1']);
        $this->assertFalse(AuditLedgerService::verify()['ok']);
    }

    public function test_removing_the_last_record_or_rewriting_the_head_is_detected(): void
    {
        $first = AuditLedgerService::record('first');
        $last = AuditLedgerService::record('last');
        $this->assertNotNull($first);
        $this->assertNotNull($last);
        DB::table('audit_ledger')->where('id', $last->id)->delete();
        $this->assertFalse(AuditLedgerService::verify()['ok']);
        DB::table('audit_ledger_head')->where('id', 1)->update(['last_id' => $first->id, 'hash' => $first->hash, 'entries' => 1]);
        $this->assertFalse(AuditLedgerService::verify()['ok']);
    }

    public function test_ciphertext_tampering_is_reported_without_leaking_data(): void
    {
        $entry = AuditLedgerService::record('encrypted', ['properties' => ['private' => 'example']]);
        $this->assertNotNull($entry);
        DB::table('audit_ledger')->where('id', $entry->id)->update(['properties_ciphertext' => 'corrupted']);
        $this->assertFalse(AuditLedgerService::verify()['ok']);
    }

    public function test_legacy_detail_encryption_preserves_v1_chain_verification(): void
    {
        $timestamp = now()->startOfSecond();
        $properties = ['identity' => 'fictional-legacy-data'];
        $dataHash = hash('sha256', json_encode(['legacy', null, null, null, $properties, $timestamp->toIso8601String()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $hash = hash('sha256', str_repeat('0', 64) . $dataHash . $timestamp->toIso8601String());
        $id = DB::table('audit_ledger')->insertGetId([
            'event' => 'legacy', 'properties' => json_encode($properties), 'hash_version' => 1,
            'data_hash' => $dataHash, 'prev_hash' => str_repeat('0', 64), 'hash' => $hash, 'created_at' => $timestamp,
        ]);
        $head = ['last_id' => $id, 'entries' => 1, 'hash' => $hash];
        DB::table('audit_ledger_head')->where('id', 1)->update($head + ['mac' => AuditLedgerService::headMac($head)]);
        $this->artisan('security:audit-encrypt')->assertSuccessful();
        $this->assertNotNull(DB::table('audit_ledger')->where('id', $id)->value('properties'));
        $this->artisan('security:audit-encrypt', ['--apply' => true])->assertSuccessful();
        $this->assertNull(DB::table('audit_ledger')->where('id', $id)->value('properties'));
        $this->assertSame($properties, \App\Models\AuditLedger::findOrFail($id)->properties);
        $this->assertTrue(AuditLedgerService::verify()['ok']);
    }
}
