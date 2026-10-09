<?php

namespace App\Notifications;

use App\Models\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VerificationAssignedNotification extends Notification
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
        return [
            'type'           => 'verification_assigned',
            'verification_id'=> $this->verification->id,
            'complaint_id'   => $this->verification->complaint_id,
            'tracking_no'    => $this->verification->complaint?->tracking_no ?? 'N/A',
            'assigned_by'    => $this->verification->assignedBy?->name ?? 'System',
            'message'        => "A verification task has been assigned to you for complaint #{$this->verification->complaint?->tracking_no}.",
            'url'            => "/verifications",
        ];
    }
}
