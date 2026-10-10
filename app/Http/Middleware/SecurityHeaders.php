<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Controllers\SpaController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        @header_remove('X-Powered-By');

        $response = $next($request);

        return $this->applyHeaders($request, $response);
    }

    public function applyHeaders(Request $request, Response $response): Response
    {
        if (app()->environment('production') && $response instanceof JsonResponse && $response->getStatusCode() >= 500) {
            $body = json_decode($response->getContent(), true);
            $safe = ['message' => 'An unexpected error occurred. Please try again later.', 'error' => 'server_error'];
            if (is_array($body) && is_string($body['code'] ?? null) && preg_match('/\A[A-Z][A-Z0-9_]{1,63}\z/', $body['code'])) {
                $safe['code'] = $body['code'];
            }
            // A server-issued UUID may be retained for support correlation.
            if (is_array($body) && is_string($body['correlation_id'] ?? null) && preg_match('/\A[0-9a-f-]{36}\z/i', $body['correlation_id'])) {
                $safe['correlation_id'] = $body['correlation_id'];
            }
            $response->setData($safe);
            $response->headers->remove('Content-Length');
        }
        $origin = $request->headers->get('Origin');
        if ($origin && !in_array($origin, config('cors.allowed_origins', []), true)) {
            $response->headers->remove('Access-Control-Allow-Origin');
            $response->headers->remove('Access-Control-Allow-Credentials');
        }

        $response->headers->remove('X-Powered-By');

        // Anti-Clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME-sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Disable legacy browser filters that can create their own XSS gadgets.
        $response->headers->set('X-XSS-Protection', '0');

        // Strict Referrer Policy
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Restrict device hardware permissions (camera + microphone allowed for live photo/voice capture)
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=(), usb=()');

        // Disallow cross-domain Flash/PDF policy files
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Cross-Origin Isolation
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // Hide internal pages from search engines & scrapers
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');

        // SPA uses external scripts and needs no inline script permission. Legacy
        // print views retain inline handlers; they still disallow objects/framing.
        if (!$response->headers->has('Content-Security-Policy')) {
            $isSpa = str_starts_with((string) $request->route()?->getActionName(), SpaController::class . '@');
            // 'wasm-unsafe-eval' lets the self-hosted OCR/PDF engines compile WebAssembly;
            // it does not permit JavaScript eval().
            $script = $isSpa ? "'self' 'wasm-unsafe-eval'" : "'self' 'unsafe-inline' https://cdn.jsdelivr.net";
            $script .= " https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/";
            $policy = str_starts_with((string) $response->headers->get('Content-Type'), 'application/json')
                ? "default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
                : "default-src 'self'; script-src {$script}; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; media-src 'self' blob:; connect-src 'self' https://www.google.com/recaptcha/; frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/; worker-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'";
            $response->headers->set('Content-Security-Policy', $policy);
        }
        if ($request->is('api/*') || $request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Pragma', 'no-cache');
        }
        // Browsers only honor HSTS over HTTPS; do not claim it on plaintext.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
