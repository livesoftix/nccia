<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS to a phone number and persist a log entry.
     *
     * @param  string       $phone          recipient number (digits only, e.g. 3001234567)
     * @param  string       $message        message text
     * @param  array        $options        ['country_code', 'lang', 'trigger', 'recipient_type',
     *                                      'subject_type', 'subject_id']
     * @return SmsLog|null
     */
    public function send(string $phone, string $message, array $options = []): ?SmsLog
    {
        $phone = preg_replace('/\D+/', '', $phone);
        if (!$phone) {
            return null;
        }

        $lang = $options['lang'] ?? 'en';
        $countryCode = $options['country_code'] ?? '+92';

        $log = null;
        try {
            $log = SmsLog::create([
                'phone'          => $phone,
                'country_code'   => $countryCode,
                'message'        => $message,
                'lang'           => $lang,
                'trigger'        => $options['trigger'] ?? null,
                'recipient_type' => $options['recipient_type'] ?? null,
                'subject_type'   => $options['subject_type'] ?? null,
                'subject_id'     => $options['subject_id'] ?? null,
                'status'         => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('SMS log could not be created', ['exception_type' => get_class($e)]);
            // Never send an untracked message when the recording dependency fails.
            return null;
        }

        if (!config('services.sms.enabled')) {
            $log?->update(['status' => 'disabled', 'response' => 'SMS disabled via config']);
            Log::info('SMS skipped (disabled)', ['id' => $log?->id, 'trigger' => $options['trigger'] ?? null]);
            return $log;
        }

        $gatewayUrl = config('services.sms.gateway_url');
        if (!$gatewayUrl) {
            $log?->update(['status' => 'failed', 'response' => 'SMS_API_URL not configured']);
            Log::error('SMS failed: gateway URL not configured', ['id' => $log?->id]);
            return $log;
        }

        try {
            $response = $this->dispatch($phone, $message);
            $body = app(TrustedOutboundHttp::class)->body($response, 8192);

            $successMatch = config('services.sms.success_match');
            $ok = $response->successful() && (!$successMatch || str_contains(strtolower($body), strtolower($successMatch)));

            $log->update([
                'status'  => $ok ? 'sent' : 'failed',
                'response'=> 'HTTP '.$response->status().'; '.($ok ? 'provider accepted' : 'provider rejected'),
                'sent_at' => $ok ? now() : null,
            ]);

            Log::info('SMS ' . ($ok ? 'sent' : 'failed'), [
                'id' => $log->id, 'status' => $log->status, 'http_status' => $response->status(),
            ]);

            return $log;
        } catch (\Throwable $e) {
            $log->update([
                'status'   => 'failed',
                'response' => 'Secure SMS delivery failed; check protected operational diagnostics.',
            ]);
            Log::error('SMS delivery failed', ['id' => $log->id, 'exception_type' => get_class($e)]);
            return $log;
        }
    }

    /**
     * Build and fire the HTTP request against the configured gateway.
     */
    protected function dispatch(string $phone, string $message): \Illuminate\Http\Client\Response
    {
        $url = config('services.sms.gateway_url');
        $method = config('services.sms.http_method', 'get');

        // Base params come from config; phone/message/sender always injected.
        $params = config('services.sms.params', []);
        $params['phone']   = $phone;
        $params['message'] = $message;
        $params['sender']  = config('services.sms.sender_id', 'NCCIA');

        if ($method !== 'post') {
            throw new \RuntimeException('SMS credentials and messages require POST transport.');
        }
        return app(TrustedOutboundHttp::class)->request((string) $url, 'sms', 8192)->asForm()->post($url, $params);
    }

    /**
     * Convenience: send both English and Roman Urdu variants.
     */
    public function sendBilingual(string $phone, string $en, string $ur, array $options = []): void
    {
        $options['lang'] = 'en';
        $this->send($phone, $en, $options);
        $options['lang'] = 'ur';
        $this->send($phone, $ur, $options);
    }

    /**
     * Send a bilingual SMS to a staff user (officer) using their phone field.
     */
    public function sendToUser(User $user, string $en, string $ur, array $options = []): void
    {
        if (!$user->phone) {
            return;
        }

        $cc = $user->country_code ?: '+92';
        $options['country_code'] = $cc;
        $options['recipient_type'] = $options['recipient_type'] ?? 'officer';

        $this->sendBilingual(
            preg_replace('/\D+/', '', $cc . $user->phone),
            $en,
            $ur,
            $options
        );
    }
}
