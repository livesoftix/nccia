<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnforceAccountSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        // Check current database state even when an authentication guard cached an older model.
        $user = $user?->fresh();
        if ($user && $token) {
            $user->withAccessToken($token);
        }
        $request->setUserResolver(fn () => $user);
        if (!$user || $user->isSuspended() || !$user->roles()->exists()) {
            return $this->deny($request, 'Account access is unavailable. Please sign in again.');
        }
        if ($token instanceof PersonalAccessToken) {
            // Legacy bearer tokens cannot serve as proof that MFA was completed.
            if ($user->mfa_enabled || $user->requiresMfa()) {
                return $this->deny($request, 'Use a verified browser session for this account.');
            }
        } else {
            if (Auth::guard('web')->viaRemember()) {
                return $this->deny($request, 'Please sign in again to confirm your credentials.');
            }
            $version = (int) ($user->security_version ?? 1);
            $stored = $request->session()->get('security_version');
            if ($stored === null && $version === 1) {
                // Upgrade an unchanged legacy session; changed accounts must reauthenticate.
                $request->session()->put('security_version', 1);
                $request->session()->put('authenticated_at', now()->timestamp);
            } elseif ((int) $stored !== $version) {
                return $this->deny($request, 'Your account changed. Please sign in again.');
            }
            if ($user->mfa_enabled && (int) $request->session()->get('mfa_verified_user') !== $user->id) {
                return $this->deny($request, 'Please sign in with your authenticator code.');
            }
        }

        $enrollmentRoute = $request->is('api/user', 'api/auth/mfa/status', 'api/auth/mfa/setup', 'api/auth/mfa/confirm');
        if ($user->mfa_enrollment_required && !$enrollmentRoute) {
            return response()->json([
                'message' => 'Set up two-factor authentication to continue.',
                'mfa_enrollment_required' => true,
            ], 423);
        }

        return app(\App\Http\Middleware\AuditedMutation::class)->handle($request, $next);
    }

    private function deny(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return $request->expectsJson()
            ? response()->json(['message' => $message], 401)
            : redirect()->route('login');
    }
}
