<?php

namespace App\Notifications;

use App\Models\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VerificationDeadlineNotification extends Notification
{
    use Queueable;

    public function __construct(public Verification $verification, public int $daysOverdue)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'verification_id' => $this->verification->id,
            'complaint_id'    => $this->verification->complaint_id,
            'tracking_no'     => $this->verification->complaint?->tracking_no ?? 'N/A',
            'days_overdue'    => $this->daysOverdue,
            'message'         => "Verification for complaint #{$this->verification->complaint?->tracking_no} is {$this->daysOverdue} day(s) overdue.",
            'url'             => "/verifications",
        ];
    }
}
