<?php

namespace App\Services;

use App\Models\CaseFile;
use App\Models\Complaint;
use App\Models\CourtCase;
use App\Models\Enquiry;
use App\Models\ForensicRequest;
use App\Models\User;
use App\Models\Verification;

/**
 * Bilingual (English + Roman Urdu) SMS message templates.
 * Every template returns both variants so the caller can pick `en` or `ur`.
 */
class SmsTemplates
{
    public static function complaintRegistered(Complaint $c, string $lang = 'en'): string
    {
        $tracking = $c->tracking_no ?: ('TMP-' . $c->id);
        $name = $c->complainant_name ?: 'Complainant';

        return $lang === 'ur'
            ? "Assalam-o-Alaikum {$name}. Aap ki complaint NCCIA/CCRC mein register ho chuki hai. Tracking Number: {$tracking}. Ye number mehfooz rakhein. Update jald diya jayega. - NCCIA"
            : "Dear {$name}, your complaint has been registered with NCCIA/CCRC. Your Tracking Number is {$tracking}. Please keep this number safe. Updates will follow shortly. - NCCIA";
    }

    public static function complaintStatusUpdate(Complaint $c, string $status, string $lang = 'en'): string
    {
        $tracking = $c->tracking_no ?: ('#' . $c->id);

        return $lang === 'ur'
            ? "Aap ki complaint {$tracking} ka status update hua hai: {$status}. Mazeed maloomat ke liye NCCIA se rabta karein. - NCCIA"
            : "Your complaint {$tracking} status has been updated to: {$status}. For details please contact NCCIA. - NCCIA";
    }

    public static function verificationAssigned(Verification $v, ?User $officer = null, string $lang = 'en'): string
    {
        $tracking = $v->complaint?->tracking_no ?: ('#' . ($v->complaint_id ?? $v->id));
        $name = $officer?->name ?: ($v->officer?->name ?: 'Officer');

        return $lang === 'ur'
            ? "Mohtaram {$name}, aap ko complaint {$tracking} ka verification assign kiya gaya hai. Detail ke liye NCCIA portal login karein. - NCCIA"
            : "Dear {$name}, verification for complaint {$tracking} has been assigned to you. Please login to the NCCIA portal for details. - NCCIA";
    }

    public static function verificationSubmitted(Verification $v, string $lang = 'en'): string
    {
        $tracking = $v->complaint?->tracking_no ?: ('#' . ($v->complaint_id ?? $v->id));

        return $lang === 'ur'
            ? "Complaint {$tracking} ka verification report submit ho chuka hai aur approval ke liye bheja gaya hai. - NCCIA"
            : "The verification report for complaint {$tracking} has been submitted and forwarded for approval. - NCCIA";
    }

    public static function appearanceNotice(Verification $v, ?User $officer = null, string $lang = 'en'): string
    {
        $complaint = $v->complaint;
        $tracking = $complaint?->tracking_no ?: ('#' . ($complaint?->id ?? $v->id));
        $officerName = $officer?->name ?: ($v->officer?->name ?: 'Verification Officer');
        $when = $v->appeared_at
            ? \Carbon\Carbon::parse($v->appeared_at)->timezone(config('app.timezone'))->format('d M Y, h:i A')
            : '[date/time]';

        return $lang === 'ur'
            ? "Assalam-o-Alaikum. Aap ki complaint {$tracking} ke verification ke liye aap ko {$officerName} ke samnay {$when} pesh hona hai. Waqt par hazir hon. - NCCIA"
            : "Assalam-o-Alaikum. For verification of your complaint {$tracking}, you are required to appear before {$officerName} on {$when}. Kindly be present on time. - NCCIA";
    }

    public static function enquiryAssigned(Enquiry $e, ?User $officer = null, string $lang = 'en'): string
    {
        $number = $e->enquiry_number ?: ('#' . $e->id);
        $name = $officer?->name ?: ($e->officer?->name ?: 'Officer');

        return $lang === 'ur'
            ? "Mohtaram {$name}, aap ko enquiry {$number} assign ki gayi hai. Detail ke liye NCCIA portal login karein. - NCCIA"
            : "Dear {$name}, enquiry {$number} has been assigned to you. Please login to the NCCIA portal for details. - NCCIA";
    }

    public static function noticeIssued(Enquiry $e, string $phone, string $lang = 'en'): string
    {
        $number = $e->enquiry_number ?: ('#' . $e->id);

        return $lang === 'ur'
            ? "Summon: Enquiry {$number} ke mutaliq aap ko pesh hona hai. Waqt par haziri zaroori hai. Mazeed details NCCIA portal par. - NCCIA"
            : "Summon: You are required to appear in connection with Enquiry {$number}. Timely appearance is mandatory. For details visit NCCIA portal. - NCCIA";
    }

    public static function nonAppearance(Enquiry $e, string $lang = 'en'): string
    {
        $number = $e->enquiry_number ?: ('#' . $e->id);

        return $lang === 'ur'
            ? "Enquiry {$number} mein aap pesh nahi hue. Qanooni karwai ki ja sakti hai. NCCIA se rabta karein. - NCCIA"
            : "You failed to appear in Enquiry {$number}. Legal action may be taken. Please contact NCCIA. - NCCIA";
    }

    public static function caseAssigned(CaseFile $c, ?User $officer = null, string $lang = 'en'): string
    {
        $fir = $c->fir_number ?: ('#' . $c->id);
        $name = $officer?->name ?: ($c->investigationOfficer?->name ?: 'Officer');

        return $lang === 'ur'
            ? "Mohtaram {$name}, aap ko case / FIR {$fir} assign kiya gaya hai. Detail ke liye NCCIA portal login karein. - NCCIA"
            : "Dear {$name}, case / FIR {$fir} has been assigned to you. Please login to the NCCIA portal for details. - NCCIA";
    }

    public static function courtHearing(CourtCase $cc, \Carbon\CarbonInterface $date, string $lang = 'en'): string
    {
        $fir = $cc->caseFile?->fir_number ?: ('#' . $cc->id);
        $court = $cc->court_name ?: 'the Court';
        $when = $date->timezone(config('app.timezone'))->format('d M Y, h:i A');

        return $lang === 'ur'
            ? "Court hearing: FIR {$fir} ki hearing {$court} mein {$when} ko hai. Haziri zaroori hai. - NCCIA"
            : "Court hearing: FIR {$fir} has a hearing at {$court} on {$when}. Attendance is required. - NCCIA";
    }

    public static function verdict(CourtCase $cc, string $verdict, string $lang = 'en'): string
    {
        $fir = $cc->caseFile?->fir_number ?: ('#' . $cc->id);

        return $lang === 'ur'
            ? "FIR {$fir} ka court verdict: {$verdict}. Mazeed maloomat NCCIA se hasil karein. - NCCIA"
            : "Court verdict for FIR {$fir}: {$verdict}. For further information please contact NCCIA. - NCCIA";
    }

    public static function forensicReportReadyToCollect(ForensicRequest $fr, string $lang = 'en'): string
    {
        $code = $fr->report_code ?: ('#' . $fr->id);
        $enq = $fr->enquiry?->enquiry_number ? "Enquiry {$fr->enquiry->enquiry_number}" : "Case";

        return $lang === 'ur'
            ? "Mohtaram Enquiry Officer, {$enq} ki Forensic Report (Code: {$code}) tayyar ho chuki hai. Baraye meharbani by-hand NCCIA Digital Forensic Lab say collect kar lein. - NCCIA"
            : "Dear Enquiry Officer, the Forensic Report (Code: {$code}) for {$enq} is ready. Please collect the physical report by-hand from NCCIA Digital Forensic Lab. - NCCIA";
    }

    public static function forensicHandedOver(ForensicRequest $fr, string $lang = 'en'): string
    {
        $code = $fr->report_code ?: ('#' . $fr->id);

        return $lang === 'ur'
            ? "Aap ka forensic report {$code} tayyar hai aur desk se hawalay kiya gaya hai. Report code mehfooz rakhein. - NCCIA"
            : "Your forensic report {$code} is ready and has been handed over at the desk. Please keep the report code safe. - NCCIA";
    }
}