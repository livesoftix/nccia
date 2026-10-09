<?php

namespace App\Notifications;

use App\Models\Enquiry;
use App\Models\EnquiryNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NoticeNonAppearanceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Enquiry $enquiry,
        public ?EnquiryNotice $notice = null,
        public bool $referred = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $count        = $this->enquiry->nonAppearanceCount();
        $enquiryLabel = $this->enquiry->enquiry_number ?: ('#' . $this->enquiry->id);
        $tracking     = $this->enquiry->complaint?->tracking_no ?? 'N/A';

        $message = $this->referred
            ? "Enquiry ({$enquiryLabel}) has {$count} non-appearances. The matter has been referred to court."
            : "A person failed to appear in Enquiry ({$enquiryLabel}). Non-appearance count: {$count}.";

        return [
            'type'           => 'notice_non_appearance',
            'referred'       => $this->referred,
            'enquiry_id'     => $this->enquiry->id,
            'enquiry_number' => $this->enquiry->enquiry_number,
            'notice_id'      => $this->notice?->id,
            'notice_number'  => $this->notice?->notice_number,
            'non_appearance_count' => $count,
            'tracking_no'    => $tracking,
            'message'        => $message,
            'url'            => '/enquiries',
        ];
    }
}
