<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EnquiryAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Enquiry $enquiry)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'enquiry_assigned',
            'enquiry_id'     => $this->enquiry->id,
            'enquiry_number' => $this->enquiry->enquiry_number,
            'complaint_id'   => $this->enquiry->complaint_id,
            'tracking_no'    => $this->enquiry->complaint?->tracking_no ?? 'N/A',
            'message'        => "An enquiry ({$this->enquiry->enquiry_number}) has been assigned to you for complaint #{$this->enquiry->complaint?->tracking_no}.",
            'url'            => "/enquiries",
        ];
    }
}
