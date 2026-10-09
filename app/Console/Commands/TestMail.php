<?php

namespace App\Console\Commands;

use App\Services\OtpMailService;
use Illuminate\Console\Command;

class TestMail extends Command
{
    protected $signature = 'nccia:test-mail {email? : Recipient email}';

    protected $description = 'Send a test OTP email to verify Gmail SMTP on this server';

    public function handle(OtpMailService $mailer): int
    {
        $email = $this->argument('email') ?: (string) env('MAIL_USERNAME');
        if (!$email) {
            $this->error('Provide an email: php artisan nccia:test-mail you@gmail.com');

            return self::FAILURE;
        }

        $this->info('Sending test OTP to ' . $email . ' ...');
        $this->line('MAIL_USERNAME=' . env('MAIL_USERNAME'));
        $this->line('MAIL_HOST=' . env('MAIL_HOST') . ' PORT=' . env('MAIL_PORT'));

        $result = $mailer->send($email, '123456', 'Test User');

        if ($result['ok']) {
            $this->info('SUCCESS via ' . $result['via']);

            return self::SUCCESS;
        }

        $this->error('FAILED: ' . $result['error']);
        $this->warn('Gmail SMTP is host pe block hai (decloud.iwhost.org).');
        $this->warn('Fix: cPanel → Email Accounts → noreply@nccia.real-erp.net banao, phir .env:');
        $this->line('  MAIL_MAILER=smtp');
        $this->line('  MAIL_HOST=localhost');
        $this->line('  MAIL_PORT=587');
        $this->line('  MAIL_USERNAME=noreply@nccia.real-erp.net');
        $this->line('  MAIL_PASSWORD=cpanel-email-password');
        $this->line('  MAIL_FROM_ADDRESS=noreply@nccia.real-erp.net');
        $this->warn('Ya MAIL_MAILER=sendmail rakho (external SMTP ke baghair).');

        return self::FAILURE;
    }
}
