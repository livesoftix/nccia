<?php

namespace Tests\Feature;

use App\Http\Controllers\SpaController;
use App\Http\Middleware\{SanitizeAndBlockAttacks, SecurityHeaders};
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HttpProtectionTest extends TestCase
{
    public function test_spa_has_strict_scripts_and_https_hsts_without_breaking_camera(): void
    {
        $request = Request::create('https://portal.test/');
        $request->setRouteResolver(fn () => new Route('GET', '/', [SpaController::class, 'index']));
        $response = (new SecurityHeaders)->handle($request, fn () => response('<html/>')->header('X-Powered-By', 'test'));
        $policy = $response->headers->get('Content-Security-Policy');
        // 'wasm-unsafe-eval' lets the self-hosted OCR/PDF engines compile WebAssembly only.
        $this->assertStringContainsString("script-src 'self' 'wasm-unsafe-eval' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/;", $policy);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', explode('script-src', $policy)[1])[0]);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringContainsString("worker-src 'self'", $policy);
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertNotNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNull($response->headers->get('X-Powered-By'));
    }

    public function test_api_responses_are_uncacheable_and_cannot_run_scripts(): void
    {
        $request = Request::create('http://portal.test/api/test');
        $response = (new SecurityHeaders)->handle($request, fn () => response()->json(['private' => true]));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_cors_rejects_unlisted_origins_and_protects_preflight_headers(): void
    {
        config(['cors.allowed_origins' => ['https://portal.test']]);
        $this->withHeaders(['Origin' => 'https://attacker.test', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/user')->assertNoContent()->assertHeaderMissing('Access-Control-Allow-Origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->withHeaders(['Origin' => 'https://portal.test', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/user')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://portal.test')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_nested_encoded_traversal_is_blocked_and_raw_tokens_are_never_logged(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(fn ($message, $context) =>
            isset($context['path_hash']) && !str_contains(json_encode([$message, $context]), 'secret-token')
            && !array_key_exists('uri', $context) && !array_key_exists('user_agent', $context)
        );
        $request = Request::create('/api/%252e%252e/private?token=secret-token');
        $response = (new SanitizeAndBlockAttacks)->handle($request, fn () => response('unsafe'));
        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_request_filter_preserves_evidence_text_and_credentials(): void
    {
        $input = ['password' => "x\0y", 'report' => 'union select <script> Urdu: تحقیق'];
        $request = Request::create('/api/narrative', 'POST', $input);
        (new SanitizeAndBlockAttacks)->handle($request, function ($request) use ($input) {
            $this->assertSame($input, $request->all());
            return response('ok');
        });
    }

    public function test_front_controller_refuses_direct_sensitive_storage_paths(): void
    {
        foreach (['/storage/signatures/private.jpg', '/uploads/complaints/private.pdf', '/STORAGE/imports/private.pdf'] as $path) {
            $response = (new SanitizeAndBlockAttacks)->handle(Request::create($path), fn () => response('unsafe'));
            $this->assertSame(403, $response->getStatusCode());
        }
    }

    public function test_production_scrubs_manual_server_error_bodies_and_retains_safe_correlation(): void
    {
        $this->app->instance('env', 'production');
        $request = Request::create('/api/test');
        $response = (new SecurityHeaders)->handle($request, fn () => response()->json([
            'message' => 'SQL password=secret /private/path', 'trace' => ['private' => true],
            'code' => 'DEPENDENCY_FAILED', 'correlation_id' => 'f32cced8-1234-4432-a323-781acd260254',
        ], 500));
        $body = json_decode($response->getContent(), true);
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertArrayNotHasKey('trace', $body);
        $this->assertSame('DEPENDENCY_FAILED', $body['code']);
        $this->assertSame('f32cced8-1234-4432-a323-781acd260254', $body['correlation_id']);
    }
}
