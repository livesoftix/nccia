<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConcurrentLoginAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $ipAddress;

    public function __construct($user, $ipAddress)
    {
        $this->user = $user;
        $this->ipAddress = $ipAddress;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Security Alert: New Login Attempt Detected',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.concurrent_login_alert',
            with: [
                'userName' => $this->user->name,
                'ipAddress' => $this->ipAddress,
                'time' => now()->toDayDateTimeString(),
            ],
        );
    }
}
