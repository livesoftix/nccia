<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyRecaptcha;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginCaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['recaptcha.enabled' => true, 'recaptcha.site_key' => 'public-test-site',
            'recaptcha.secret_key' => 'private-test-secret',
            'recaptcha.allowed_hostnames' => ['nccia.real-erp.net']]);
        Http::preventStrayRequests();
        Route::post('/api/captcha-probe', fn () => response()->json(['passed' => true]))
            ->middleware(VerifyRecaptcha::class);
    }

    public function test_all_login_routes_reject_missing_tokens_before_authentication(): void
    {
        Http::fake();
        foreach (['/api/login', '/api/forensic/login'] as $path) {
            $this->postJson($path, ['email' => 'officer@example.com', 'password' => 'irrelevant'])
                ->assertUnprocessable()->assertJsonValidationErrors('captcha_token');
        }
        $this->from('/login')->post('/login', ['email' => 'officer@example.com', 'password' => 'irrelevant'])
            ->assertRedirect('/login')->assertSessionHasErrors('captcha_token');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_public_configuration_never_discloses_secret(): void
    {
        $response = $this->getJson('/api/auth/captcha')->assertOk()
            ->assertExactJson(['enabled' => true, 'site_key' => 'public-test-site']);
        $this->assertStringNotContainsString('private-test-secret', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_verified_token_for_configured_hostname_passes(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response([
            'success' => true, 'hostname' => 'nccia.real-erp.net',
        ])]);
        $this->postJson('/api/captcha-probe', ['captcha_token' => 'valid-token'])
            ->assertOk()->assertJsonPath('passed', true);
        Http::assertSent(fn ($request) => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'private-test-secret' && $request['response'] === 'valid-token'
            && !isset($request['password']));
    }

    public function test_invalid_expired_replayed_and_wrong_domain_tokens_are_rejected(): void
    {
        foreach ([['success' => false], ['success' => false, 'error-codes' => ['timeout-or-duplicate']],
            ['success' => true, 'hostname' => 'attacker.example'], ['success' => true],
            ['success' => 'true', 'hostname' => 'nccia.real-erp.net']] as $result) {
            Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::response($result)]);
            $this->postJson('/api/captcha-probe', ['captcha_token' => 'bad-token'])
                ->assertUnprocessable()->assertJsonValidationErrors('captcha_token');
        }
    }

    public function test_provider_outage_cannot_bypass_verification(): void
    {
        Http::fake(['www.google.com/recaptcha/api/siteverify' => Http::failedConnection()]);
        $this->postJson('/api/captcha-probe', ['captcha_token' => 'token'])
            ->assertUnprocessable()->assertJsonValidationErrors('captcha_token');
    }

    public function test_enabled_but_unconfigured_captcha_fails_closed(): void
    {
        config(['recaptcha.secret_key' => '']);
        Http::fake();
        $this->getJson('/api/auth/captcha')->assertStatus(503);
        $this->postJson('/api/captcha-probe', ['captcha_token' => 'token'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_explicitly_disabled_captcha_does_not_contact_google(): void
    {
        config(['recaptcha.enabled' => false]);
        Http::fake();
        $this->getJson('/api/auth/captcha')->assertOk()->assertExactJson(['enabled' => false, 'site_key' => null]);
        $this->postJson('/api/captcha-probe')->assertOk();
        Http::assertNothingSent();
    }

    public function test_malformed_and_oversized_tokens_do_not_contact_google(): void
    {
        Http::fake();
        foreach ([['array'], str_repeat('x', 8193)] as $token) {
            $this->postJson('/api/captcha-probe', ['captcha_token' => $token])
                ->assertUnprocessable()->assertJsonValidationErrors('captcha_token');
        }
        Http::assertNothingSent();
    }

    public function test_login_csp_allows_widget_without_allowing_arbitrary_frames(): void
    {
        $policy = $this->get('/login')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://www.gstatic.com/recaptcha/', $policy);
        $this->assertStringContainsString("frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
    }
}
