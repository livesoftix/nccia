<?php

namespace App\Logging;

use Monolog\LogRecord;

/** Applies to operational logs; the protected audit ledger has its own data policy. */
class RedactSensitiveLogs
{
    public function __invoke($logger): void
    {
        $logger->pushProcessor($this->process(...));
    }

    public function process(LogRecord $record): LogRecord
    {
        return $record->with(message: $this->text($record->message),
            context: $this->clean($record->context), extra: $this->clean($record->extra));
    }

    private function clean(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            if (preg_match('/password|passwd|secret|token|authorization|cookie|otp|mfa_code|recovery_code|api_key|private_key|bindings|request_body|response_body/i', (string) $key)) {
                $safe[$key] = '[redacted]';
            } elseif ($value instanceof \Throwable) {
                $safe[$key] = ['type' => get_class($value), 'file' => basename($value->getFile()), 'line' => $value->getLine()];
            } elseif (is_array($value)) {
                $safe[$key] = $this->clean($value);
            } elseif (is_string($value)) {
                $safe[$key] = $this->text($value);
            } elseif (is_object($value)) {
                $safe[$key] = ['type' => get_class($value)];
            } else {
                $safe[$key] = $value;
            }
        }
        return $safe;
    }

    private function text(string $value): string
    {
        foreach (['app.key', 'security.audit_hmac_key', 'mail.mailers.smtp.password',
            'services.sms.params.api_key', 'services.sms.params.password', 'services.postmark.key', 'services.ses.secret'] as $key) {
            $secret = config($key);
            if (is_string($secret) && strlen($secret) >= 4) {
                $value = str_replace($secret, '[redacted]', $value);
            }
        }
        $value = preg_replace('/\bBearer\s+[^\s,;]+/i', 'Bearer [redacted]', $value);
        $value = preg_replace('/\b(password|passwd|secret|token|api_key|otp|mfa_code)\s*[=:]\s*[^\s,;&]+/i', '$1=[redacted]', $value);
        $value = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '[email-redacted]', $value);
        $value = preg_replace('/\b[0-9]{5}-[0-9]{7}-[0-9]\b/', '[identity-redacted]', $value);
        // Single-line structured events prevent an attacker from forging another log entry.
        return str_replace(["\r", "\n", "\0"], ['\\r', '\\n', ''], $value);
    }
}
