<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VerifyRecaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('recaptcha.enabled')) {
            return $next($request);
        }

        $secret = (string) config('recaptcha.secret_key');
        $hosts = array_map('strtolower', config('recaptcha.allowed_hostnames', []));
        abort_if(!$secret || !config('recaptcha.site_key') || !$hosts, 503, 'Human verification is unavailable.');

        $data = $request->validate([
            'captcha_token' => ['required', 'string', 'max:8192'],
        ], ['captcha_token.required' => 'Please complete the “I’m not a robot” verification.']);

        try {
            // Fixed HTTPS endpoint, no redirects or retries: tokens are single use.
            // Never send credentials or log the secret/token with provider errors.
            $response = Http::asForm()->connectTimeout(3)->timeout(10)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $data['captcha_token'],
                ]);
            $result = $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            $result = null;
        }

        if (!is_array($result) || ($result['success'] ?? false) !== true
            || !is_string($result['hostname'] ?? null)
            || !in_array(strtolower($result['hostname']), $hosts, true)) {
            throw ValidationException::withMessages([
                'captcha_token' => 'Human verification failed or expired. Please complete the CAPTCHA again.',
            ]);
        }

        return $next($request);
    }
}
