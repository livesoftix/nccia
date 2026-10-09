<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $timeout = config('session.idle_timeout', 15); // minutes

            if ($timeout > 0) {
                $lastActivity = session('last_activity', now());
                $elapsed = abs(now()->diffInMinutes($lastActivity));

                if ($elapsed >= $timeout) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    if ($request->expectsJson()) {
                        return response()->json(['message' => 'Session expired due to inactivity'], 401);
                    }

                    return redirect()->route('login');
                }
            }

            $started = $request->session()->get('authenticated_at', now()->timestamp);
            $absoluteMinutes = (int) config('security.session_absolute_minutes', 480);
            if ($absoluteMinutes > 0 && now()->timestamp - (int) $started >= $absoluteMinutes * 60) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return $request->expectsJson()
                    ? response()->json(['message' => 'Session expired. Please sign in again.'], 401)
                    : redirect()->route('login');
            }
            $request->session()->put('authenticated_at', $started);

            // Anti-Session Hijacking: Lock session to original client IP and Device Fingerprint
            $currentIp = $request->ip();
            $currentAgent = substr((string) $request->userAgent(), 0, 150);
            $sessionIp = session('auth_ip');
            $sessionAgent = session('auth_user_agent');

            if (! $sessionIp) {
                session(['auth_ip' => $currentIp, 'auth_user_agent' => $currentAgent]);
            } elseif ($sessionIp !== $currentIp || $sessionAgent !== $currentAgent) {
                // Potential session hijacking / stolen cookie replay from different device or network!
                \Illuminate\Support\Facades\Log::warning('Session hijacking attempt blocked', [
                    'user_id'       => Auth::id(),
                    'expected_ip'   => $sessionIp,
                    'attempt_ip'    => $currentIp,
                    'attempt_agent' => $currentAgent,
                ]);

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session terminated: IP or Device mismatch detected.'], 401);
                }

                return redirect()->route('login');
            }

            session(['last_activity' => now()]);
            \Illuminate\Support\Facades\Cache::put('user_online_' . Auth::id(), true, now()->addMinutes(2));
        }

        return $next($request);
    }
}
