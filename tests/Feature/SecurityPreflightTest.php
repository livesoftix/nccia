<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityPreflightTest extends TestCase
{
    use RefreshDatabase;

    private function safeConfiguration(): void
    {
        config(['app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://nccia.example.invalid',
            'security.audit_hmac_key' => bin2hex(random_bytes(32)),
            'security.audit.require_writes' => true,
            'session.secure' => true, 'session.http_only' => true, 'session.encrypt' => true,
            'session.same_site' => 'strict', 'session.driver' => 'database', 'session.lifetime' => 60, 'session.idle_timeout' => 15,
            'security.session_absolute_minutes' => 240, 'security.mfa.require_all' => true,
            'hashing.driver' => 'argon2id', 'hashing.argon.memory' => 65536, 'hashing.argon.time' => 4,
            'hashing.rehash_on_login' => true, 'security.uploads.scan_required' => true,
            'services.sms.enabled' => false, 'services.adp.approved_disclosure' => false,
            'mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => 'smtps', 'mail.mailers.smtp.port' => 465,
            'mail.mailers.smtp.url' => null, 'mail.mailers.smtp.host' => 'smtp.example.invalid',
            'mail.mailers.smtp.username' => 'fictional-account', 'mail.mailers.smtp.password' => 'fictional-sensitive-value',
            'mail.mailers.smtp.stream.ssl.verify_peer' => true, 'mail.mailers.smtp.stream.ssl.verify_peer_name' => true,
            'mail.mailers.smtp.stream.ssl.allow_self_signed' => false]);
    }

    public function test_default_inspects_no_database_and_never_prints_secret_values(): void
    {
        $this->safeConfiguration();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        $this->assertSame(2, Artisan::call('security:preflight', ['--json' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['database_inspected']);
        $this->assertSame('incomplete', $report['status']);
        $this->assertSame([], $queries);
        $this->assertStringNotContainsString(config('app.key'), $output);
        $this->assertStringNotContainsString(config('security.audit_hmac_key'), $output);
        $this->assertStringNotContainsString('fictional-sensitive-value', $output);
    }

    public function test_insecure_flags_fail_the_release_check(): void
    {
        $this->safeConfiguration();
        config(['app.debug' => true, 'session.secure' => false, 'hashing.driver' => 'bcrypt',
            'mail.mailers.smtp.stream.ssl.verify_peer' => false]);
        $this->assertSame(1, Artisan::call('security:preflight', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], 'status', 'id');
        foreach (['debug_disabled', 'secure_sessions', 'argon2id_passwords', 'smtp_transport'] as $id) {
            $this->assertSame('fail', $checks[$id]);
        }
    }

    public function test_explicit_database_option_checks_schema_and_audit_without_changing_records(): void
    {
        $this->safeConfiguration();
        $head = (array) DB::table('audit_ledger_head')->first();
        // The migration's initial head was authenticated with the test key before the config override.
        DB::table('audit_ledger_head')->where('id', 1)->update(['mac' => \App\Services\AuditLedgerService::headMac($head)]);
        $before = json_encode(DB::table('audit_ledger_head')->first());
        $this->assertSame(2, Artisan::call('security:preflight', ['--json' => true, '--database' => true]));
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], 'status', 'id');
        $this->assertSame('pass', $checks['database_security_schema']);
        $this->assertSame('pass', $checks['audit_integrity']);
        $this->assertSame('unverified', $checks['database_runtime_grants_tls']);
        $this->assertSame($before, json_encode(DB::table('audit_ledger_head')->first()));
        DB::table('audit_ledger_head')->where('id', 1)->update(['mac' => str_repeat('0', 64)]);
        $this->assertSame(1, Artisan::call('security:preflight', ['--json' => true, '--database' => true]));
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], 'status', 'id');
        $this->assertSame('fail', $checks['audit_integrity']);
    }

    public function test_enabled_external_services_require_exact_https_destinations(): void
    {
        $this->safeConfiguration();
        config(['services.sms.enabled' => true, 'services.sms.gateway_url' => 'http://untrusted.example.invalid',
            'services.sms.allowed_hosts' => ['approved.example.invalid'], 'services.sms.allowed_ports' => [443],
            'services.sms.http_method' => 'get']);
        $this->assertSame(1, Artisan::call('security:preflight', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], 'status', 'id');
        $this->assertSame('fail', $checks['sms_outbound']);
    }

    public function test_audit_key_reuse_is_rejected_even_when_encoding_differs(): void
    {
        $this->safeConfiguration();
        config(['security.audit_hmac_key' => base64_decode(substr(config('app.key'), 7), true)]);
        $this->assertSame(1, Artisan::call('security:preflight', ['--json' => true]));
        $checks = array_column(json_decode(Artisan::output(), true)['checks'], 'status', 'id');
        $this->assertSame('fail', $checks['dedicated_audit_key']);
    }
}
