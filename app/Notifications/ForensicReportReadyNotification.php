<?php

namespace App\Notifications;

use App\Models\ForensicRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForensicReportReadyNotification extends Notification
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
            'type'    => 'forensic_report_ready',
            'message' => 'Forensic report ready: code ' . ($this->request->report_code ?: $this->request->request_no) . '. Collect from lab room and hand to EO.',
            'url'     => '/forensic/requests/' . $this->request->id,
            'request_id' => $this->request->id,
            'report_code' => $this->request->report_code,
        ];
    }
}
