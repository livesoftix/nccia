<?php

namespace App\Services;

/** RFC 6238 / RFC 4226: 160-bit random secrets, SHA-1, six digits, 30 seconds. */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->encode(random_bytes(20));
    }

    public function code(string $secret, int $timestamp, int $digits = 6): string
    {
        if ($timestamp < 0 || !in_array($digits, [6, 8], true)) {
            throw new \InvalidArgumentException('Invalid TOTP parameters.');
        }
        $counter = intdiv($timestamp, 30);
        $binary = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $binary, $this->decode($secret), true);
        $offset = ord($hash[19]) & 15;
        $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($number % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function matchingCounter(string $secret, string $code, int $timestamp): ?int
    {
        if (!preg_match('/\A[0-9]{6}\z/', $code)) {
            return null;
        }
        $counter = intdiv($timestamp, 30);
        // Permit one step of device clock skew, and reject replay in MfaService.
        foreach ([$counter, $counter - 1, $counter + 1] as $candidate) {
            if ($candidate >= 0 && hash_equals($this->code($secret, $candidate * 30), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    private function encode(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $result = '';
        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits) {
            $result .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $result;
    }

    private function decode(string $secret): string
    {
        if (!preg_match('/\A[A-Z2-7]+\z/', $secret)) {
            throw new \InvalidArgumentException('Invalid TOTP secret.');
        }
        $buffer = 0;
        $bits = 0;
        $result = '';
        foreach (str_split($secret) as $letter) {
            $buffer = ($buffer << 5) | strpos(self::ALPHABET, $letter);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 255);
            }
            $buffer &= (1 << $bits) - 1;
        }

        return $result;
    }
}
