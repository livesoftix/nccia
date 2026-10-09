<?php

namespace Tests\Unit;

use App\Mail\GmailSender;
use App\Mail\OtpMail;
use App\Services\SecureMailService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SensitiveMailTransportTest extends TestCase
{
    public function test_smtp_transport_requires_tls_and_uses_default_certificate_verification_without_sending_mail(): void
    {
        config(['mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.scheme' => 'smtp']);
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        $this->assertTrue($transport->isTlsRequired());
        $options = $transport->getStream()->getStreamOptions();
        $this->assertNotSame(false, $options['ssl']['verify_peer'] ?? true);
        $this->assertNotSame(false, $options['ssl']['verify_peer_name'] ?? true);
        $this->assertNotSame(true, $options['ssl']['allow_self_signed'] ?? false);
    }

    public function test_insecure_configuration_fails_before_transport_and_never_falls_back_to_mail_or_log(): void
    {
        Mail::fake();
        config(['mail.mailers.smtp.require_tls' => false]);
        $result = app(SecureMailService::class)->send('recipient@example.test', new OtpMail('123456', 'Recipient'));
        $this->assertFalse($result['ok']);
        Mail::assertNothingSent();
        $source = file_get_contents(app_path('Mail/GmailSender.php'));
        $this->assertMatchesRegularExpression("/env\('MAIL_USERNAME',\s*''\)/", $source);
        $this->assertMatchesRegularExpression("/env\('MAIL_PASSWORD',\s*''\)/", $source);
        $this->assertStringNotContainsString("'verify_peer' => false", $source);
        $this->assertStringNotContainsString("'verify_peer_name' => false", $source);
    }
}
