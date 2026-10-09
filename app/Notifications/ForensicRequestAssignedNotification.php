<?php

namespace App\Notifications;

use App\Models\ForensicRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForensicRequestAssignedNotification extends Notification
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
        $isNew = $this->request->status === 'submitted';

        return [
            'type'    => $isNew ? 'forensic_submitted' : 'forensic_assigned',
            'message' => $isNew
                ? 'New seizure note ' . $this->request->request_no . ' submitted for AD Forensic review.'
                : 'Forensic request ' . $this->request->request_no . ' assigned to you. Open it to get report code.',
            'url'     => '/forensic/requests/' . $this->request->id,
            'request_id' => $this->request->id,
            'request_no' => $this->request->request_no,
        ];
    }
}
