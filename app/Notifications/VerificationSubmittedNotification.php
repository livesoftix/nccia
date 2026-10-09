<?php

namespace App\Notifications;

use App\Models\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VerificationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public Verification $verification)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tracking = $this->verification->complaint?->tracking_no ?? $this->verification->complaint_id;
        $officer = $this->verification->officer?->name ?? 'Verification Officer';

        return [
            'type'            => 'verification_submitted',
            'verification_id' => $this->verification->id,
            'complaint_id'    => $this->verification->complaint_id,
            'tracking_no'     => $tracking,
            'officer_name'    => $officer,
            'recommendation'  => $this->verification->recommendation,
            'message'         => "{$officer} ne verification report submit ki — Complaint #{$tracking}. Approve / Review karein.",
            'url'             => '/verifications',
        ];
    }
}
