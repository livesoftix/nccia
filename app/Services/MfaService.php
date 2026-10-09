<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MfaService
{
    public function __construct(private TotpService $totp) {}

    /** Consumption is serialized on the account so TOTP/recovery codes cannot be replayed. */
    public function consume(User $user, string $code): bool
    {
        return $this->consumeProof($user, $code) !== null;
    }

    public function consumeProof(User $user, string $code): ?array
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (!$locked->mfa_confirmed_at || !$locked->mfa_secret) {
                return null;
            }
            $counter = $this->totp->matchingCounter($locked->mfa_secret, $code, now()->timestamp);
            if ($counter !== null && ($locked->mfa_last_counter === null || $counter > $locked->mfa_last_counter)) {
                $locked->forceFill(['mfa_last_counter' => $counter])->save();
                return ['user_id' => $locked->id, 'security_version' => (int) $locked->security_version, 'mfa_verified' => true];
            }
            // Recovery codes are high-entropy random values and stored only as hashes.
            if (!preg_match('/\A[a-f0-9]{20}\z/', $code)) {
                return null;
            }
            $hashes = $locked->mfa_recovery_codes ?? [];
            foreach ($hashes as $index => $hash) {
                if (Hash::check($code, $hash)) {
                    unset($hashes[$index]);
                    $locked->forceFill(['mfa_recovery_codes' => array_values($hashes)])->save();
                    return ['user_id' => $locked->id, 'security_version' => (int) $locked->security_version, 'mfa_verified' => true];
                }
            }

            return null;
        });
    }

    public function recoveryCodes(): array
    {
        return array_map(fn () => bin2hex(random_bytes(10)), range(1, 10));
    }

    public static function markSession(Request $request, User $user, bool $mfaVerified): void
    {
        $request->session()->put('security_version', (int) ($user->security_version ?? 1));
        $request->session()->put('authenticated_at', now()->timestamp);
        if ($mfaVerified) {
            $request->session()->put('mfa_verified_user', $user->id);
        } else {
            $request->session()->forget('mfa_verified_user');
        }
    }
}
