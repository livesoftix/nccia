<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\User;
use App\Models\Verification;
use Carbon\Carbon;

class ComplainantNotifyService
{
    public function registrationMessage(Complaint $complaint): string
    {
        $tracking = $complaint->tracking_no ?: ('TMP-' . $complaint->id);
        $name = $complaint->complainant_name ?: 'Complainant';

        return "Assalam-o-Alaikum {$name}. Aap ki complaint NCCIA/CCRC mein register ho chuki hai. "
            . "Aap ka Tracking Number: {$tracking}. "
            . "Baraye meherbani ye number mehfooz rakhein. Mazeed update jald diya jayega. — NCCIA / CCRC";
    }

    public function appearanceMessage(Verification $verification, ?User $officer = null): string
    {
        $complaint = $verification->complaint;
        $tracking = $complaint?->tracking_no ?: ('#' . ($complaint?->id ?? $verification->id));
        $officerName = $officer?->name
            ?: $verification->officer?->name
            ?: 'Verification Officer';

        $when = $verification->appeared_at
            ? Carbon::parse($verification->appeared_at)->timezone(config('app.timezone'))->format('d M Y, h:i A')
            : '[date/time]';

        return "Assalam-o-Alaikum. Aap ki complaint number {$tracking} ke mutaliq verification ke liye "
            . "aap ko {$officerName} ke samnay {$when} par pesh hona hai. "
            . "Baraye meherbani waqt par hazir hon. — NCCIA / CCRC";
    }

    public function phoneDigits(?Complaint $complaint): ?string
    {
        if (!$complaint) {
            return null;
        }

        $raw = ($complaint->contact_country_code ?: '+92') . ($complaint->contact_no ?: '');
        $digits = preg_replace('/\D+/', '', $raw);

        return $digits ?: null;
    }

    public function whatsappUrl(?Complaint $complaint, string $message): ?string
    {
        $digits = $this->phoneDigits($complaint);
        if (!$digits) {
            return null;
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    /**
     * Persist registration notice on the complaint and return WhatsApp payload.
     */
    public function notifyRegistration(Complaint $complaint, string $via = 'whatsapp'): array
    {
        $message = $this->registrationMessage($complaint);
        $url = $this->whatsappUrl($complaint, $message);

        $complaint->forceFill([
            'registration_message'     => $message,
            'registration_notify_via'  => $via,
            'registration_notified_at' => now(),
        ])->save();

        return [
            'message'      => $message,
            'via'          => $via,
            'whatsapp_url' => $url,
            'phone'        => $this->phoneDigits($complaint),
            'notified_at'  => optional($complaint->registration_notified_at)->toISOString(),
        ];
    }
}
