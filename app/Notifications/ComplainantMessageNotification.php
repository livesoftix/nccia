<?php

namespace App\Notifications;

use App\Models\Verification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplainantMessageNotification extends Notification
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
        $c = $this->verification->complaint;

        return [
            'verification_id' => $this->verification->id,
            'complaint_id'    => $c ? $c->id : null,
            'tracking_no'     => $c ? $c->tracking_no : null,
            'officer_id'      => $this->verification->verification_officer_id,
            'appeared_at'     => $this->verification->appeared_at,
            'message'         => "Verification officer has recorded a message to the complainant for "
                . ($c ? ('complaint ' . $c->tracking_no . '.') : 'the complaint.'),
        ];
    }
}
