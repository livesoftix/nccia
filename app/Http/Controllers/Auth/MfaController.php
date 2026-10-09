<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLedgerService;
use App\Services\MfaService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class MfaController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'enabled' => $user->mfa_enabled,
            'required' => $user->requiresMfa(),
            'recovery_codes_remaining' => count($user->mfa_recovery_codes ?? []),
        ]);
    }

    public function setup(Request $request, TotpService $totp)
    {
        $this->confirmPassword($request);
        abort_if($request->user()->mfa_enabled, 409, 'Two-factor authentication is already enabled.');
        $secret = $totp->generateSecret();
        $request->session()->put('mfa_setup', [
            'user_id' => $request->user()->id,
            'secret' => Crypt::encryptString($secret),
            'security_version' => (int) $request->user()->security_version,
            'expires' => now()->addMinutes(10)->timestamp,
        ]);
        $issuer = (string) config('security.mfa.issuer');
        $uri = 'otpauth://totp/' . rawurlencode($issuer . ':' . $request->user()->email) . '?' . http_build_query([
            'secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => 6, 'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json(['secret' => $secret, 'uri' => $uri])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, TotpService $totp, MfaService $mfa)
    {
        $data = $request->validate(['code' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);
        $pending = $request->session()->get('mfa_setup');
        abort_unless($pending && $pending['user_id'] === $request->user()->id && $pending['expires'] > now()->timestamp,
            422, 'Setup expired. Start again.');
        $secret = Crypt::decryptString($pending['secret']);
        $counter = $totp->matchingCounter($secret, $data['code'], now()->timestamp);
        abort_if($counter === null, 422, 'Invalid authenticator code.');
        $codes = $mfa->recoveryCodes();
        $user = DB::transaction(function () use ($request, $pending, $counter, $codes, $secret) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_if($user->mfa_enabled, 409, 'Two-factor authentication is already enabled.');
            abort_unless((int) ($pending['security_version'] ?? 0) === (int) $user->security_version,
                409, 'Your account changed. Start setup again.');
            $user->forceFill([
                'mfa_secret' => $secret,
                'mfa_confirmed_at' => now(),
                'mfa_last_counter' => $counter,
                'mfa_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes),
                'security_version' => (int) $user->security_version + 1,
            ])->save();
            return $user;
        });
        $request->session()->forget('mfa_setup');
        $request->session()->regenerate();
        MfaService::markSession($request, $user, true);
        \Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
        AuditLedgerService::record('mfa.enabled', ['user_id' => $user->id]);

        return response()->json(['message' => 'Two-factor authentication enabled.', 'recovery_codes' => $codes])
            ->header('Cache-Control', 'no-store');
    }

    public function disable(Request $request, MfaService $mfa)
    {
        $this->confirmPassword($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        $user = DB::transaction(function () use ($request, $mfa, $data, $user) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->confirmLockedPassword($request, $locked);
            abort_if($locked->requiresMfa(), 403, 'Two-factor authentication is required for your account.');
            abort_unless($mfa->consume($locked, $data['code']), 422, 'Invalid or already used code.');
            $locked->refresh()->forceFill([
                'mfa_secret' => null, 'mfa_confirmed_at' => null, 'mfa_last_counter' => null,
                'mfa_recovery_codes' => null, 'security_version' => (int) $locked->security_version + 1,
            ])->save();
            return $locked;
        }, 3);
        $request->session()->regenerate();
        MfaService::markSession($request, $user, false);
        \Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
        AuditLedgerService::record('mfa.disabled', ['user_id' => $user->id]);

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    public function regenerateRecoveryCodes(Request $request, MfaService $mfa)
    {
        $this->confirmPassword($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $codes = $mfa->recoveryCodes();
        $user = DB::transaction(function () use ($request, $mfa, $data, $codes) {
            $locked = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $this->confirmLockedPassword($request, $locked);
            abort_unless($mfa->consume($locked, $data['code']), 422, 'Invalid or already used code.');
            $locked->refresh()->forceFill([
                'mfa_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes),
            ])->save();
            $locked->invalidateAuthentication();
            return $locked;
        }, 3);
        $request->session()->regenerate();
        MfaService::markSession($request, $user, true);
        \Illuminate\Support\Facades\Auth::guard('web')->setUser($user);
        AuditLedgerService::record('mfa.recovery_codes_regenerated', ['user_id' => $user->id]);
        return response()->json(['message' => 'Recovery codes replaced. Store them offline.', 'recovery_codes' => $codes])
            ->header('Cache-Control', 'no-store');
    }

    private function confirmLockedPassword(Request $request, User $user): void
    {
        abort_unless((int) $user->security_version === (int) $request->user()->security_version
            && Hash::check($request->input('password'), $user->password), 409, 'Your account changed. Please sign in again.');
    }

    private function confirmPassword(Request $request): void
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:1024']]);
        abort_unless(Hash::check($data['password'], $request->user()->password), 422, 'Current password is incorrect.');
    }
}
