<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditedMutation;
use App\Logging\RedactSensitiveLogs;
use App\Services\AuditCheckpointService;
use App\Services\AuditLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

class OperationalSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_redact_nested_credentials_messages_and_injection(): void
    {
        config(['mail.mailers.smtp.password' => 'synthetic-mail-credential']);
        $record = new LogRecord(new \DateTimeImmutable(), 'test', Level::Error,
            "Delivery failed synthetic-mail-credential token=synthetic-value\nforged entry person@example.test",
            ['nested' => ['Authorization' => 'Bearer synthetic-value', 'password' => 'synthetic-mail-credential'],
                'exception' => new \RuntimeException('SELECT credentials and synthetic-mail-credential')]);
        $safe = (new RedactSensitiveLogs)->process($record);
        $json = json_encode([$safe->message, $safe->context], JSON_THROW_ON_ERROR);
        foreach (['synthetic-mail-credential', 'synthetic-value', 'person@example.test', 'SELECT credentials'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertStringNotContainsString("\n", $safe->message);
        $this->assertSame(\RuntimeException::class, $safe->context['exception']['type']);
    }

    public function test_retained_checkpoint_accepts_extension_and_rejects_rewritten_valid_chain(): void
    {
        AuditLedgerService::record('first');
        $service = app(AuditCheckpointService::class);
        $checkpoint = $service->create();
        AuditLedgerService::record('second');
        $this->assertTrue($service->verify($checkpoint));
        $tampered = $checkpoint; $tampered['entries']++;
        $this->assertFalse($service->verify($tampered));
        DB::table('audit_ledger')->delete();
        $head = ['last_id' => 0, 'entries' => 0, 'hash' => str_repeat('0', 64)];
        DB::table('audit_ledger_head')->where('id', 1)->update($head + ['mac' => AuditLedgerService::headMac($head)]);
        AuditLedgerService::record('rewritten');
        $this->assertTrue(AuditLedgerService::verify()['ok']);
        $this->assertFalse($service->verify($checkpoint));
    }

    public function test_missing_audit_head_blocks_mutation_before_changes(): void
    {
        config(['security.audit.require_writes' => true]);
        DB::table('audit_ledger_head')->delete();
        $request = Request::create('/api/synthetic', 'POST');
        $this->app->instance('request', $request);
        $called = false;
        $response = app(AuditedMutation::class)->handle($request, function () use (&$called) {
            $called = true; return response()->json(['ok' => true]);
        });
        $this->assertSame(503, $response->getStatusCode());
        $this->assertFalse($called);
    }

    public function test_checkpoint_command_never_overwrites_an_existing_checkpoint(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path('checkpoint.json');
        $this->artisan('security:audit-checkpoint', ['--output' => $path])->assertSuccessful();
        $original = file_get_contents($path);
        $this->artisan('security:audit-checkpoint', ['--output' => $path])->assertFailed();
        $this->assertSame($original, file_get_contents($path));
        $this->artisan('security:audit-checkpoint', ['--verify' => $path])->assertSuccessful();
        $this->assertStringNotContainsString('user_name', $original);
    }

    public function test_audit_failure_rolls_back_even_if_controller_swallows_exception(): void
    {
        config(['security.audit.require_writes' => true]);
        $request = Request::create('/api/synthetic', 'POST');
        $this->app->instance('request', $request);
        $before = DB::table('audit_ledger_head')->value('mac');
        $response = app(AuditedMutation::class)->handle($request, function () {
            DB::table('audit_ledger_head')->update(['mac' => str_repeat('a', 64)]);
            try { AuditLedgerService::record('security.synthetic'); } catch (\Throwable) {}
            return response()->json(['ok' => true]);
        });
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame($before, DB::table('audit_ledger_head')->value('mac'));
        $this->assertTrue(AuditLedgerService::verify()['ok']);
    }

    public function test_successful_mutation_and_audit_commit_together(): void
    {
        config(['security.audit.require_writes' => true]);
        $request = Request::create('/api/synthetic', 'POST');
        $this->app->instance('request', $request);
        $response = app(AuditedMutation::class)->handle($request, fn () => response()->json(['ok' => true]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('mutation.completed', DB::table('audit_ledger')->value('event'));
        $this->assertTrue(AuditLedgerService::verify()['ok']);
    }

    public function test_scheduled_integrity_command_reports_tail_removal_as_failure(): void
    {
        $entry = AuditLedgerService::record('synthetic');
        $this->artisan('security:audit-verify')->assertSuccessful();
        DB::table('audit_ledger')->where('id', $entry->id)->delete();
        $this->artisan('security:audit-verify')->assertFailed();
        $this->assertNull(AuditLedgerService::record('must.not.append.after.tampering'));
    }
}
