<?php

namespace Tests\Feature;

use App\Services\SmsService;
use App\Services\TrustedOutboundHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutboundSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $addresses = ['1.1.1.1']): TrustedOutboundHttp
    {
        return new class($addresses) extends TrustedOutboundHttp {
            public function __construct(private array $addresses) {}
            protected function resolveHost(string $host): array { return $this->addresses; }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.sms.enabled' => true, 'services.sms.gateway_url' => 'https://sms.example.test/send',
            'services.sms.allowed_hosts' => ['sms.example.test'], 'services.sms.allowed_ports' => [443],
            'services.sms.http_method' => 'post', 'services.sms.success_match' => 'ok',
            'services.sms.params' => ['api_key' => 'synthetic-test-credential']]);
        Http::preventStrayRequests();
        $this->app->instance(TrustedOutboundHttp::class, $this->client());
    }

    public function test_tls_dns_pinning_redirect_and_size_controls_are_enforced(): void
    {
        $options = $this->client()->request('https://sms.example.test/send', 'sms', 8192)->getOptions();
        $this->assertTrue($options['verify']);
        $this->assertFalse($options['allow_redirects']);
        $this->assertSame('', $options['proxy']);
        $this->assertSame(['sms.example.test:443:1.1.1.1'], $options['curl'][CURLOPT_RESOLVE]);
        $this->expectException(\RuntimeException::class);
        $options['progress'](0, 8193, 0, 0);
    }

    public function test_unapproved_hosts_private_dns_query_credentials_and_plaintext_are_rejected(): void
    {
        foreach (['http://sms.example.test/send', 'https://unapproved.example.test/send',
            'https://user:pass@sms.example.test/send', 'https://sms.example.test/send?token=example',
            'https://sms.example.test:444/send', 'https://sms.example.test/send#fragment'] as $url) {
            try { $this->client()->request($url, 'sms'); $this->fail('Unsafe endpoint was accepted.'); }
            catch (\RuntimeException) { $this->assertTrue(true); }
        }
        foreach (['127.0.0.1', '169.254.169.254', '100.64.0.1', '::ffff:127.0.0.1', '192.0.2.1', '10.0.0.1'] as $ip) {
            try { $this->client([$ip])->request('https://sms.example.test/send', 'sms'); $this->fail('Non-public address was accepted.'); }
            catch (\RuntimeException) { $this->assertTrue(true); }
        }
    }

    public function test_sms_posts_secrets_in_body_and_does_not_store_provider_echo(): void
    {
        Http::fake(['sms.example.test/*' => Http::response('ok synthetic-test-credential', 200)]);
        $log = app(SmsService::class)->send('3001234567', 'synthetic message');
        $this->assertSame('sent', $log->status);
        $this->assertStringNotContainsString('synthetic-test-credential', $log->response);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && !str_contains($request->url(), '?') && $request['message'] === 'synthetic message');
    }

    public function test_http_failure_is_not_success_even_when_provider_body_matches(): void
    {
        Http::fake(['sms.example.test/*' => Http::response('ok', 500)]);
        $this->assertSame('failed', app(SmsService::class)->send('3001234567', 'synthetic')->status);
    }

    public function test_legacy_get_sms_and_untracked_delivery_fail_closed(): void
    {
        config(['services.sms.http_method' => 'get']);
        $this->assertSame('failed', app(SmsService::class)->send('3001234567', 'synthetic')->status);
        Http::assertNothingSent();
        config(['services.sms.http_method' => 'post']);
        Schema::drop('sms_logs');
        $this->assertNull(app(SmsService::class)->send('3001234567', 'synthetic'));
        Http::assertNothingSent();
    }

    public function test_local_http_cannot_be_enabled_in_production(): void
    {
        config(['services.adp.allowed_hosts' => ['127.0.0.1'], 'services.adp.allowed_ports' => [8001],
            'services.adp.allow_local_http' => true]);
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(\RuntimeException::class);
        $this->client()->request('http://127.0.0.1:8001/api/v1/extract', 'adp');
    }
}
