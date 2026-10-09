<?php

namespace App\Notifications;

use App\Models\ForensicRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForensicReportHandedOverNotification extends Notification
{
    use Queueable;

    public function __construct(public ForensicRequest $request)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'forensic_handed_over',
            'message' => 'Forensic report ' . ($this->request->report_code ?: $this->request->request_no) . ' handed over by Desk. Collect by hand with this code.',
            'url'     => '/enquiries/' . ($this->request->enquiry_id ?: '') . '/edit',
            'request_id' => $this->request->id,
            'report_code' => $this->request->report_code,
        ];
    }
}
