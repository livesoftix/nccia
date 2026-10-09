<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PriorityCaseNotification extends Notification
{
    use Queueable;

    public function __construct(public Complaint $complaint)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'complaint_id'  => $this->complaint->id,
            'tracking_no'   => $this->complaint->tracking_no,
            'priority_type' => $this->complaint->priority_type,
            'message'       => "High priority complaint {$this->complaint->tracking_no} (" . strtoupper($this->complaint->priority_type) . ') has been assigned for verification.',
        ];
    }
}
