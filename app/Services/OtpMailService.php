<?php

namespace App\Services;

use App\Mail\OtpMail;

class OtpMailService
{
    public function send(string $to, string $otp, string $name): array
    {
        return app(SecureMailService::class)->send($to, new OtpMail($otp, $name));
    }
}
