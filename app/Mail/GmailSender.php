<?php

namespace App\Mail;

use Illuminate\Support\Facades\Log;

/**
 * Fast Gmail SMTP delivery for OTP (single attempt, short timeout).
 * Avoids PHP mail() + multi-port retries that hang shared hosting for 60s+.
 */
class GmailSender
{
    private const CONNECT_TIMEOUT = 8;
    private const IO_TIMEOUT = 10;

    public static function send(string $to, string $subject, string $body): bool
    {
        $username = trim((string) env('MAIL_USERNAME', ''));
        $password = preg_replace('/\s+/', '', (string) env('MAIL_PASSWORD', ''));
        $fromName = (string) env('MAIL_FROM_NAME', 'NCCIA Portal');

        if ($username === '' || $password === '') {
            Log::error('GmailSender: MAIL_USERNAME / MAIL_PASSWORD missing');

            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($username, FILTER_VALIDATE_EMAIL)
            || preg_match('/[\r\n]/', $to . $username . $subject)) {
            return false;
        }

        // One fast path only: Gmail 587 STARTTLS
        $ok = self::sendSmtp(
            'smtp.gmail.com',
            587,
            'starttls',
            $to,
            $subject,
            $body,
            $username,
            $password,
            $fromName
        );

        if ($ok) {
            Log::info('GmailSender: delivery accepted.');

            return true;
        }

        Log::error('GmailSender: delivery failed.');

        return false;
    }

    private static function sendSmtp(
        string $host,
        int $port,
        string $mode,
        string $to,
        string $subject,
        string $body,
        string $username,
        string $password,
        string $fromName
    ): bool {
        $remote = ($mode === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if (!$socket) {
            Log::warning("GmailSender: connect failed {$remote}: {$errno} {$errstr}");

            return false;
        }

        stream_set_timeout($socket, self::IO_TIMEOUT);

        try {
            self::expect($socket, 220);

            self::write($socket, "EHLO nccia.local\r\n");
            self::readAll($socket);

            if ($mode === 'starttls') {
                self::write($socket, "STARTTLS\r\n");
                self::expect($socket, 220);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS crypto failed');
                }
                self::write($socket, "EHLO nccia.local\r\n");
                self::readAll($socket);
            }

            self::write($socket, "AUTH LOGIN\r\n");
            self::expect($socket, 334);
            self::write($socket, base64_encode($username) . "\r\n");
            self::expect($socket, 334);
            self::write($socket, base64_encode($password) . "\r\n");
            $auth = self::read($socket);
            if (!str_starts_with(trim($auth), '235')) {
                throw new \RuntimeException('SMTP AUTH failed: ' . trim($auth));
            }

            self::write($socket, "MAIL FROM:<{$username}>\r\n");
            self::expect($socket, 250);
            self::write($socket, "RCPT TO:<{$to}>\r\n");
            self::expect($socket, 250);
            self::write($socket, "DATA\r\n");
            self::expect($socket, 354);

            $headers = 'From: ' . self::encodeAddress($fromName, $username) . "\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

            $body = str_replace(["\r\n", "\r"], "\n", $body);
            $body = preg_replace('/^\./m', '..', $body);
            $body = str_replace("\n", "\r\n", $body);
            self::write($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            self::expect($socket, 250);

            self::write($socket, "QUIT\r\n");
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            Log::warning('GmailSender: SMTP delivery failed.', ['exception_type' => get_class($e)]);
            @fclose($socket);

            return false;
        }
    }

    private static function encodeAddress(string $name, string $email): string
    {
        $safe = str_replace(['"', "\r", "\n"], '', $name);

        return "\"{$safe}\" <{$email}>";
    }

    private static function write($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    private static function read($socket): string
    {
        $line = fgets($socket, 515);

        return $line === false ? '' : $line;
    }

    private static function readAll($socket): string
    {
        $buf = '';
        while ($line = fgets($socket, 515)) {
            $buf .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $buf;
    }

    private static function expect($socket, int $code): string
    {
        $line = self::readAll($socket);
        if (!str_starts_with(trim($line), (string) $code)) {
            throw new \RuntimeException("Expected {$code}, got: " . trim($line));
        }

        return $line;
    }
}
