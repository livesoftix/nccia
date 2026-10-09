<?php

namespace App\Notifications;

use App\Models\CaseFile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CaseAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public CaseFile $caseFile)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'case_assigned',
            'case_id'        => $this->caseFile->id,
            'fir_no'         => $this->caseFile->fir_no,
            'enquiry_id'     => $this->caseFile->enquiry_id,
            'message'        => "A DAC case (FIR #{$this->caseFile->fir_no}) has been assigned to you for investigation.",
            'url'            => "/cases",
        ];
    }
}
