<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetMailService;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const TOKEN_EXPIRE_MINUTES = 60;

    public function forgot(Request $request, PasswordResetMailService $mailer)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        $key = 'password-reset:' . hash('sha256', $email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many reset attempts. Try again in {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, 3600);

        $genericMessage = 'If that email is registered, a password reset link has been sent.';

        $user = User::where('email', $email)->first();
        if ($user && !$user->isSuspended() && $user->roles()->exists()) {
            $plainToken = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token'      => Hash::make($plainToken),
                    'created_at' => now(),
                ]
            );

            $resetUrl = rtrim((string) config('app.url'), '/') . '/reset-password?' . http_build_query([
                'token' => $plainToken,
                'email' => $email,
            ]);

            $result = $mailer->send($user->email, $user->name, $resetUrl);
            if (!$result['ok']) {
                Log::error('Password reset email could not be sent', [
                    'email' => $email,
                    'error' => $result['error'],
                ]);
            }
        }

        return response()->json(['message' => $genericMessage]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email'                 => ['required', 'email', 'max:255'],
            'token'                 => ['required', 'string', 'size:64'],
            'password'              => StrongPassword::rules(true, true),
            'password_confirmation' => ['required', 'string', 'max:128'],
        ]);

        $email = strtolower(trim($data['email']));
        $key = 'password-reset-verify:' . hash('sha256', $email);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['message' => 'Too many reset attempts. Please try again later.'], 429);
        }
        RateLimiter::hit($key, 300);
        // The account and token are locked in one transaction. Successful use
        // changes the password and consumes the token atomically, including retries.
        $updated = DB::transaction(function () use ($data, $email) {
            $user = User::where('email', $email)->lockForUpdate()->first();
            $record = DB::table('password_reset_tokens')->where('email', $email)->lockForUpdate()->first();
            if (!$user || $user->isSuspended() || !$user->roles()->exists() || !$record) {
                return false;
            }
            $createdAt = $record->created_at ? \Carbon\Carbon::parse($record->created_at) : null;
            if (!$createdAt || $createdAt->lte(now()->subMinutes(self::TOKEN_EXPIRE_MINUTES)) || $createdAt->isFuture()
                || !Hash::check($data['token'], $record->token)) {
                return false;
            }
            $user->update(['password' => $data['password']]);
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return true;
        }, 3);
        if (!$updated) {
            return response()->json(['message' => 'Invalid or expired reset link.'], 422);
        }
        RateLimiter::clear($key);
        if ($request->hasSession()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Password updated successfully. You can sign in now.']);
    }
}
