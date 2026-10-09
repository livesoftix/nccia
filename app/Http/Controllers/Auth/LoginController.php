<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\IpDetectionService;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('web')->check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $userForCheck = \App\Models\User::where('email', $credentials['email'])->first();
        if ($userForCheck && \Illuminate\Support\Facades\Hash::check($credentials['password'], $userForCheck->password)) {
            $userForCheck = $this->upgradePasswordHash($userForCheck, $credentials['password']);
            if ($response = $this->verifyMfa($request, $userForCheck, $key)) {
                return $response;
            }
            if (\Illuminate\Support\Facades\Cache::has('user_online_' . $userForCheck->id)) {
                $ip = $request->ip();
                try {
                    \Illuminate\Support\Facades\Mail::to($userForCheck->email)->send(new \App\Mail\ConcurrentLoginAlert($userForCheck, $ip));
                } catch (\Throwable $e) {}
                \Illuminate\Support\Facades\Cache::put('login_alert_' . $userForCheck->id, $ip, now()->addMinutes(2));
                return back()->withErrors(['email' => 'Your account is currently logged in elsewhere. We have sent an alert to your email.']);
            }
        }

        if (Auth::guard('web')->attempt($credentials, false)) {
            $user = Auth::guard('web')->user();

            // Deny suspended accounts (and accounts with access revoked).
            if ($user->isSuspended() || !$user->roles()->count()) {
                $reason = $user->isSuspended() ? 'suspended' : 'access_revoked';
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $this->recordLoginHistory($request, $reason, $user->id);
                return back()->withErrors([
                    'email' => $user->isSuspended()
                        ? 'This account is suspended. Contact administrator.'
                        : 'Access revoked. Contact administrator.',
                ])->onlyInput('email');
            }

            if ($response = $this->completeSecurityChecks($request, $user)) {
                return $response;
            }
            $request->session()->regenerate();
            MfaService::markSession($request, $user, (bool) $request->attributes->get('login_proof')['mfa_verified']);
            $request->session()->put('auth_ip', $request->ip());
            $request->session()->put('auth_user_agent', substr((string) $request->userAgent(), 0, 150));
            $request->session()->put('last_activity', now());
            RateLimiter::clear($key);
            $this->recordLoginHistory($request, 'web', Auth::guard('web')->id());
            return redirect('/');
        }

        RateLimiter::hit($key);
        $this->recordLoginHistory($request, 'failed_web');

        return back()->withErrors([
            'email' => 'Invalid email or password',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $userId = auth()->id();
        $clientIp = $request->ip();

        // Update the latest login history with logout time
        try {
            if ($userId) {
                LoginHistory::where('user_id', $userId)
                    ->where('ip_address', $clientIp)
                    ->whereNull('logged_out_at')
                    ->latest('logged_in_at')
                    ->first()
                    ?->update(['logged_out_at' => now()]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Logout history update failed: ' . $e->getMessage());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId) {
            \Illuminate\Support\Facades\Cache::forget('user_online_' . $userId);
        }

        return redirect()->route('login');
    }

    public function apiLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $this->recordLoginHistory($request, 'rate_limited');
            User::notifySupervisors(
                'security_bruteforce',
                "Login locked out for {$request->input('email')} (IP {$request->ip()})",
                ['ip' => $request->ip(), 'email' => $request->input('email'), 'retry_after' => $seconds]
            );
            return response()->json([
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429);
        }

        $userForCheck = \App\Models\User::where('email', $credentials['email'])->first();
        if ($userForCheck && \Illuminate\Support\Facades\Hash::check($credentials['password'], $userForCheck->password)) {
            $userForCheck = $this->upgradePasswordHash($userForCheck, $credentials['password']);
            if ($response = $this->verifyMfa($request, $userForCheck, $key)) {
                return $response;
            }
            if (\Illuminate\Support\Facades\Cache::has('user_online_' . $userForCheck->id)) {
                $ip = $request->ip();
                try {
                    \Illuminate\Support\Facades\Mail::to($userForCheck->email)->send(new \App\Mail\ConcurrentLoginAlert($userForCheck, $ip));
                } catch (\Throwable $e) {}
                \Illuminate\Support\Facades\Cache::put('login_alert_' . $userForCheck->id, $ip, now()->addMinutes(2));
                return response()->json(['message' => 'Your account is currently logged in elsewhere. We have sent an alert to your email.'], 403);
            }
        }

        if (Auth::guard('web')->attempt($credentials, false)) {
            $user = Auth::guard('web')->user();

            // Deny login if user has no roles (access revoked)
            if (!$user->roles()->count()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $this->recordLoginHistory($request, 'access_revoked', $user->id);
                return response()->json(['message' => 'Access revoked. Contact administrator.'], 403);
            }

            // Deny login if the account is suspended.
            if ($user->isSuspended()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $this->recordLoginHistory($request, 'suspended', $user->id);
                return response()->json(['message' => 'This account is suspended. Contact administrator.'], 403);
            }

            if ($response = $this->completeSecurityChecks($request, $user)) {
                return $response;
            }
            $request->session()->regenerate();
            MfaService::markSession($request, $user, (bool) $request->attributes->get('login_proof')['mfa_verified']);
            $request->session()->put('auth_ip', $request->ip());
            $request->session()->put('auth_user_agent', substr((string) $request->userAgent(), 0, 150));
            $request->session()->put('last_activity', now());
            RateLimiter::clear($key);
            $this->recordLoginHistory($request, 'api', $user->id);
            return response()->json([
                'user' => $user->load('roles', 'zone', 'circle', 'permissions'),
            ]);
        }

        RateLimiter::hit($key);
        $this->recordLoginHistory($request, 'failed_api');
        $attempts = RateLimiter::attempts($key);
        $remaining = max(0, 3 - $attempts);

        return response()->json([
            'message' => 'Invalid email or password',
            'remaining' => $remaining,
        ], 401);
    }

    public function apiLogout(Request $request)
    {
        $userId = Auth::guard('web')->id();
        $clientIp = $request->ip();

        // Update the latest login history with logout time
        try {
            if ($userId) {
                LoginHistory::where('user_id', $userId)
                    ->where('ip_address', $clientIp)
                    ->whereNull('logged_out_at')
                    ->latest('logged_in_at')
                    ->first()
                    ?->update(['logged_out_at' => now()]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('API logout history update failed: ' . $e->getMessage());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId) {
            \Illuminate\Support\Facades\Cache::forget('user_online_' . $userId);
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Authenticate for the Forensic Portal (only users with forensic access).
     */
    public function apiForensicLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $this->recordLoginHistory($request, 'rate_limited');
            User::notifySupervisors(
                'security_bruteforce',
                "Login locked out for {$request->input('email')} (IP {$request->ip()})",
                ['ip' => $request->ip(), 'email' => $request->input('email'), 'retry_after' => $seconds]
            );
            return response()->json([
                'message' => "Too many attempts. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429);
        }

        $userForCheck = \App\Models\User::where('email', $credentials['email'])->first();
        if ($userForCheck && \Illuminate\Support\Facades\Hash::check($credentials['password'], $userForCheck->password)) {
            $userForCheck = $this->upgradePasswordHash($userForCheck, $credentials['password']);
            if ($response = $this->verifyMfa($request, $userForCheck, $key)) {
                return $response;
            }
            if (\Illuminate\Support\Facades\Cache::has('user_online_' . $userForCheck->id)) {
                $ip = $request->ip();
                try {
                    \Illuminate\Support\Facades\Mail::to($userForCheck->email)->send(new \App\Mail\ConcurrentLoginAlert($userForCheck, $ip));
                } catch (\Throwable $e) {}
                \Illuminate\Support\Facades\Cache::put('login_alert_' . $userForCheck->id, $ip, now()->addMinutes(2));
                return response()->json(['message' => 'Your account is currently logged in elsewhere. We have sent an alert to your email.'], 403);
            }
        }

        if (!Auth::guard('web')->attempt($credentials, false)) {
            RateLimiter::hit($key);
            $this->recordLoginHistory($request, 'failed_forensic');
            $attempts = RateLimiter::attempts($key);
            $remaining = max(0, 3 - $attempts);
            return response()->json([
                'message' => 'Invalid email or password',
                'remaining' => $remaining,
            ], 401);
        }

        $user = Auth::guard('web')->user();

        if (!$user->roles()->count()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $this->recordLoginHistory($request, 'access_revoked', $user->id);
            return response()->json(['message' => 'Access revoked. Contact administrator.'], 403);
        }

        if ($user->isSuspended()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $this->recordLoginHistory($request, 'suspended', $user->id);
            return response()->json(['message' => 'This account is suspended. Contact administrator.'], 403);
        }

        if (!$user->isForensic()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $this->recordLoginHistory($request, 'denied_forensic', $user->id);
            return response()->json([
                'message' => 'This account does not have Forensic Portal access.',
            ], 403);
        }

        if ($response = $this->completeSecurityChecks($request, $user)) {
            return $response;
        }
        $request->session()->regenerate();
        MfaService::markSession($request, $user, (bool) $request->attributes->get('login_proof')['mfa_verified']);
        $request->session()->put('auth_ip', $request->ip());
        $request->session()->put('auth_user_agent', substr((string) $request->userAgent(), 0, 150));
        $request->session()->put('last_activity', now());
        RateLimiter::clear($key);
        $this->recordLoginHistory($request, 'forensic', $user->id);

        return response()->json([
            'user' => $user->load('roles', 'zone', 'circle', 'permissions'),
        ]);
    }

    private function verifyMfa(Request $request, User $user, string $key)
    {
        $request->attributes->set('login_proof', [
            'user_id' => $user->id, 'security_version' => (int) ($user->security_version ?? 1), 'mfa_verified' => false,
        ]);
        if (!$user->mfa_enabled) {
            return null;
        }
        $code = $request->input('mfa_code');
        if (!is_string($code) || $code === '') {
            $message = 'Enter your authenticator code or a recovery code.';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'mfa_required' => true], 428)
                : back()->withErrors(['mfa_code' => $message])->onlyInput('email');
        }
        $proof = strlen($code) <= 20 ? app(MfaService::class)->consumeProof($user, $code) : null;
        if (!$proof) {
            RateLimiter::hit($key, 60);
            $this->recordLoginHistory($request, 'failed_mfa', $user->id);
            $message = 'Invalid or already used authenticator/recovery code.';
            return $request->expectsJson()
                ? response()->json(['message' => $message, 'mfa_required' => true], 422)
                : back()->withErrors(['mfa_code' => $message])->onlyInput('email');
        }
        $request->attributes->set('login_proof', $proof);
        return null;
    }

    private function upgradePasswordHash(User $user, string $password): User
    {
        // Rehash before pinning the MFA proof: rehashing increments the account
        // version and must not invalidate this otherwise valid first sign-in.
        return DB::transaction(function () use ($user, $password) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (Hash::check($password, $locked->password) && Hash::needsRehash($locked->password)) {
                $locked->update(['password' => $password]);
            }
            return $locked;
        }, 3);
    }

    private function completeSecurityChecks(Request $request, User &$user)
    {
        $user = $user->fresh();
        $proof = $request->attributes->get('login_proof');
        if (!$proof || $proof['user_id'] !== $user->id || $proof['security_version'] !== (int) $user->security_version
            || ($user->mfa_enabled && !$proof['mfa_verified']) || $user->isSuspended() || !$user->roles()->exists()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $message = 'Your account changed during sign in. Please sign in again.';
            return $request->expectsJson() ? response()->json(['message' => $message], 409)
                : back()->withErrors(['email' => $message])->onlyInput('email');
        }
        Auth::guard('web')->setUser($user);
        return null;
    }

    private function recordLoginHistory(Request $request, string $method, ?int $userId = null): void
    {
        try {
            $targetUserId = $userId ?: auth()->id();
            if (!$targetUserId) {
                $targetUser = \App\Models\User::where('email', $request->input('email'))->first();
                $targetUserId = $targetUser?->id;
            }

            if ($targetUserId) {
                $ipService = app(IpDetectionService::class);
                $clientIp = $ipService->getClientIp($request);
                $realIp = $ipService->getRealIp($request);
                $proxyHeaders = $ipService->getProxyHeaders($request);
                $isSpoofed = $ipService->detectSpoofing($request);

                LoginHistory::create([
                    'user_id' => $targetUserId,
                    'ip_address' => $clientIp ?: '0.0.0.0',
                    'real_ip' => $realIp ?: $clientIp,
                    'proxy_headers' => $proxyHeaders ?: null,
                    'is_spoofed' => $isSpoofed,
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'login_method' => $method,
                    'logged_in_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Login history recording failed: ' . $e->getMessage());
        }
    }

    private function throttleKey(Request $request): string
    {
        return 'login:' . hash('sha256', strtolower((string) $request->input('email')));
    }
}
