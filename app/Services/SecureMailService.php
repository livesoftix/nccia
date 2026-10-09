<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Sensitive mail uses one authenticated TLS transport, with no downgrade fallback. */
class SecureMailService
{
    public function send(string $to, Mailable $message): array
    {
        $smtp = config('mail.mailers.smtp', []);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)
            || empty($smtp['host']) || empty($smtp['username']) || empty($smtp['password'])
            || !filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)
            || !in_array((int) ($smtp['port'] ?? 0), [465, 587], true)
            || !($smtp['require_tls'] ?? false) || !($smtp['verify_peer'] ?? false)) {
            Log::warning('Sensitive mail rejected: secure SMTP configuration is incomplete.');
            return ['ok' => false, 'via' => null, 'error' => 'Secure mail delivery is unavailable. Contact the administrator.'];
        }
        try {
            Mail::mailer('smtp')->to($to)->send($message);
            Log::info('Sensitive mail accepted by configured SMTP transport.');
            return ['ok' => true, 'via' => 'smtp', 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Sensitive mail delivery failed.', ['exception_type' => get_class($e)]);
            return ['ok' => false, 'via' => null, 'error' => 'Secure mail delivery is unavailable. Contact the administrator.'];
        }
    }
}
