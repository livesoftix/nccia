<?php

namespace App\Services;

use App\Mail\PasswordResetMail;

class PasswordResetMailService
{
    public function send(string $to, string $name, string $resetUrl): array
    {
        return app(SecureMailService::class)->send($to, new PasswordResetMail($name, $resetUrl));
    }
}
