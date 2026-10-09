<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Enquiry;
use App\Models\EnquiryActivity;
use App\Models\EnquiryNotice;

class PrintService
{
    public function __construct(private readonly QrService $qr)
    {
    }

    /**
     * 80mm thermal complaint slip with QR verification.
     */
    public function complaintSlip(Complaint $complaint): string
    {
        try {
            $complaint->loadMissing(['circle', 'verification.officer', 'latestVerificationReport.creator']);
        } catch (\Throwable $e) {
            // Safe fallback if relations are missing
        }

        $number   = $complaint->tracking_no ?: ($complaint->slip_number ?: ('#' . $complaint->id));
        $qrHtml   = $this->documentQr('complaint', (int) $complaint->id, [
            'Type'         => 'Complaint Receipt',
            'Complaint No' => $number,
            'Complainant'  => $complaint->complainant_name ?: '—',
            'Circle'       => $complaint->circle?->name ?? '—',
        ], 92, 'Scan to verify');

        $circle   = e($complaint->circle?->name ?? '—');
        $name     = e($complaint->complainant_name ?: '—');
        $cnic     = e($complaint->cnic ?: '—');
        $phone    = trim(($complaint->contact_country_code ? '+' . ltrim((string) $complaint->contact_country_code, '+') . ' ' : '') . ($complaint->contact_no ?? ''));
        $phone    = e($phone !== '' ? $phone : '—');
        $date     = $complaint->report_date ? \Illuminate\Support\Carbon::parse($complaint->report_date)->format('d/m/Y') : '—';
        $time     = $complaint->entry_time ? \Illuminate\Support\Carbon::parse($complaint->entry_time)->format('h:i A') : '—';
        $address  = e($complaint->address ?: '—');
        $offence  = e($complaint->offence_type ?? '—');
        $source   = e($complaint->source ? ucfirst(str_replace('_', ' ', $complaint->source)) : '—');
        $operator = e($complaint->operator_name ?: '—');

        $voName = $complaint->verification?->officer?->name
            ?: ($complaint->latestVerificationReport?->creator?->name
                ?: ($complaint->verification_officer_name ?: null));

        if (!$voName && $complaint->verification?->verification_officer_id) {
            $voUser = \App\Models\User::find($complaint->verification->verification_officer_id);
            $voName = $voUser?->name;
        }

        $voDisplay = $voName ? e($voName) : 'Pending Assignment';
        $issuedAt = now()->format('d/m/Y h:i A');
        $ncciaLogo = url('images/images.jpg');
        $interiorLogo = url('images/pak-govt-logo.png');
        $numberE  = e($number);

        return <<<HTML
        <div class="slip">
          <div class="head">
            <div class="gov-title">GOVERNMENT OF PAKISTAN</div>
            <table class="slip-lh">
              <tr>
                <td class="slip-lh-l"><img src="{$ncciaLogo}" alt="NCCIA" class="slip-lh-logo" /></td>
                <td class="slip-lh-c">
                  <div class="slip-nccia">NCCIA</div>
                  <div class="org">NATIONAL CYBER CRIME<br/>INVESTIGATION AGENCY</div>
                  <div class="org-ur" dir="rtl">قومی سائبر کرائم تفتیش ایجنسی</div>
                </td>
                <td class="slip-lh-r"><img src="{$interiorLogo}" alt="Ministry of Interior" class="slip-lh-logo" /></td>
              </tr>
            </table>
            <div class="tag-wrap">
              <span class="tag">COMPLAINT RECEIPT</span>
            </div>
          </div>

          <div class="num-box">
            <div class="num-label">COMPLAINT / TRACKING NO.</div>
            <div class="num-val">{$numberE}</div>
          </div>

          <table class="kv">
            <tr><td class="k">Complainant</td><td class="v">{$name}</td></tr>
            <tr><td class="k">CNIC</td><td class="v">{$cnic}</td></tr>
            <tr><td class="k">Contact</td><td class="v">{$phone}</td></tr>
            <tr><td class="k">Circle</td><td class="v">{$circle}</td></tr>
            <tr><td class="k">Verif. Officer</td><td class="v"><strong>{$voDisplay}</strong></td></tr>
            <tr><td class="k">Report Date</td><td class="v">{$date}</td></tr>
            <tr><td class="k">Report Time</td><td class="v">{$time}</td></tr>
            <tr><td class="k">Source</td><td class="v">{$source}</td></tr>
            <tr><td class="k">Offence</td><td class="v">{$offence}</td></tr>
            <tr><td class="k">Address</td><td class="v">{$address}</td></tr>
          </table>

          <div class="note">
            Please keep this slip for future reference.
            Quote the Complaint No. for all follow-ups.
          </div>

          <table class="sign-row">
            <tr>
              <td class="sign">
                <div class="sign-line"></div>
                <div class="small">Verif. Officer / Desk</div>
                <div class="tiny">{$voDisplay}</div>
              </td>
              <td class="sign">
                <div class="sign-line"></div>
                <div class="small">Complainant</div>
              </td>
            </tr>
          </table>

          <div class="qr-block">
            {$qrHtml}
          </div>

          <div class="foot">
            <div class="tiny">Issued: {$issuedAt}</div>
            <div class="tiny">NCCIA — Serving the Nation</div>
          </div>
        </div>
        HTML;
    }

    public function slipPrintDocument(Complaint $complaint): string
    {
        return $this->document(
            $this->complaintSlip($complaint),
            '@page { size: 80mm auto; margin: 2mm; }
             body { margin:0; padding:0; background:#fff; }
             .slip { width: 74mm; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 11px; color:#000; }
             .head { text-align:center; border-bottom: 2px solid #000; padding-bottom: 7px; margin-bottom: 6px; }
             .gov-title { font-size: 8.5px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: #111; margin-bottom: 4px; text-align: center; }
             .slip-lh { width:100%; border-collapse:collapse; margin: 0 auto; }
             .slip-lh td { border:none; vertical-align:middle; padding:0; }
             .slip-lh-l { width:42px; text-align:left; }
             .slip-lh-r { width:42px; text-align:right; }
             .slip-lh-c { text-align:center; padding: 0 4px; }
             .slip-lh-logo { width:38px; height:38px; object-fit:contain; display:block; margin: 0 auto; }
             .slip-nccia { font-size:13px; font-weight:900; letter-spacing:2px; color:#000; line-height: 1.1; }
             .org { font-weight:800; font-size:8.5px; line-height:1.2; margin-top:2px; letter-spacing:0.2px; text-align:center; color:#111; }
             .org-ur { font-size:9.5px; font-weight:700; margin-top:2px; text-align:center; line-height:1.2; }
             .tag-wrap { margin-top:5px; text-align:center; }
             .tag { display:inline-block; padding:2px 8px; border:1.5px solid #000; border-radius:3px; font-weight:800; font-size:9px; letter-spacing:0.8px; text-align:center; background:#f4f4f4; text-transform: uppercase; }
             .num-box { text-align:center; border:2px solid #000; border-radius: 5px; padding:5px 6px; margin:7px auto; width:88%; background:#fafafa; }
             .num-label { font-size:8px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; color:#333; border-bottom:1px dashed #aaa; padding-bottom:2px; margin-bottom:2px; }
             .num-val { font-size:17px; font-weight:900; letter-spacing:0.8px; color:#000; }
             .kv { width:100%; border-collapse:collapse; margin:2px auto 6px; }
             .kv td { padding:3px 0; vertical-align:top; border-bottom:1px dotted #bbb; }
             .kv tr:last-child td { border-bottom:none; }
             .k { width:78px; font-weight:700; color:#222; }
             .v { word-break:break-word; text-align:right; }
             .note { font-size:9.5px; line-height:1.35; text-align:center; border:2px solid #000; padding:5px 4px; margin:6px auto; width:92%; }
             .sign-row { width:100%; border-collapse:collapse; margin:10px auto 6px; }
             .sign { width:50%; text-align:center; vertical-align:top; padding:0 3px; }
             .sign-line { border-top:2px solid #000; margin:18px 0 3px; }
             .qr-block { text-align:center; border-top:2px dashed #000; padding-top:6px; margin-top:4px; }
             .qr { width:92px; height:92px; }
             .foot { text-align:center; margin-top:4px; border-top:2px solid #000; padding-top:4px; }
             .small { font-size:9.5px; font-weight:700; text-align:center; }
             .tiny { font-size:8.5px; margin-top:1px; text-align:center; }
             .muted { color:#444; word-break:break-all; line-height:1.2; }',
            false
        );
    }

    /**
     * Full A4 complaint report: complainant, offence, description, accused (with identity files).
     */
    public function complaintReportPrintDocument(Complaint $complaint): string
    {
        try {
            $complaint->loadMissing(['circle', 'verification.officer', 'latestVerificationReport.creator']);
        } catch (\Throwable $e) {
            // Safe fallback
        }

        $logo = url('images/images.jpg');
        $issuedAt = now()->format('d/m/Y h:i A');

        $number  = $complaint->tracking_no ?: ($complaint->slip_number ?: ('#' . $complaint->id));
        $circle  = e($complaint->circle?->name ?? '—');
        $name    = e($complaint->complainant_name ?: '—');
        $father  = e($complaint->father_name ?: '—');
        $cnic    = e($complaint->cnic ?: '—');
        $phone   = trim(($complaint->contact_country_code ? '+' . ltrim((string) $complaint->contact_country_code, '+') . ' ' : '') . ($complaint->contact_no ?? ''));
        $phone   = e($phone !== '' ? $phone : '—');
        $whatsapp = e($complaint->whatsapp_no ?: '—');
        $gender  = e($complaint->gender ? ucfirst($complaint->gender) : '—');
        $email   = e($complaint->email ?: '—');
        $address = e($complaint->address ?: '—');
        $district = e($complaint->district ?: '—');
        $passportNo = e($complaint->passport_no ?: '—');
        $nationality = e($complaint->nationality ?: '—');

        $reportDate = $complaint->report_date ? \Illuminate\Support\Carbon::parse($complaint->report_date)->format('d/m/Y') : '—';
        $occurrence = $complaint->occurrence_date ? \Illuminate\Support\Carbon::parse($complaint->occurrence_date)->format('d/m/Y') : '—';
        $offence  = e($complaint->offence_type ?: '—');
        $priority = e(ucfirst(str_replace('_', ' ', $complaint->priority_type ?? '—')));
        $source   = e($complaint->source ? ucfirst(str_replace('_', ' ', $complaint->source)) : '—');
        $amount   = $complaint->amount_involved !== null ? e(number_format((float) $complaint->amount_involved, 2)) : '—';
        $diary    = e($complaint->diary_no ?: '—');
        $operator = e($complaint->operator_name ?: '—');
        $status   = e(ucfirst(str_replace('_', ' ', $complaint->status ?? '—')));
        $description = nl2br(e($complaint->description ?: '—'));

        $voName = $complaint->verification?->officer?->name
            ?: ($complaint->latestVerificationReport?->creator?->name
                ?: ($complaint->verification_officer_name ?: null));

        if (!$voName && $complaint->verification?->verification_officer_id) {
            $voUser = \App\Models\User::find($complaint->verification->verification_officer_id);
            $voName = $voUser?->name;
        }
        $voDisplay = $voName ? e($voName) : 'Under Verification';

        $laws = collect($complaint->laws ?: [])->implode(', ');
        $laws = $laws ? e($laws) : '—';

        $complaintFiles = '';
        $complaintDocs = [
            ['label' => 'CNIC Front',   'value' => $complaint->cnic_front],
            ['label' => 'CNIC Back',    'value' => $complaint->cnic_back],
            ['label' => 'Passport',     'value' => $complaint->passport_attachment],
            ['label' => 'Photo',        'value' => $complaint->picture],
            ['label' => 'Attachment',   'value' => $complaint->attachment],
        ];
        foreach ($complaintDocs as $doc) {
            if (!empty($doc['value'])) {
                $complaintFiles .= '<span class="alink"><a href="' . e(url($doc['value'])) . '">' . $doc['label'] . '</a></span>';
            }
        }
        if (!$complaintFiles) {
            $complaintFiles = '<div class="muted">No attachments</div>';
        }

        // Financial record (bank transfer details)
        $bankSender = trim(($complaint->bank_name_sender ?? '') . ' ' . ($complaint->account_no_sender ?? ''));
        $bankReceiver = trim(($complaint->bank_name_receiver ?? '') . ' ' . ($complaint->account_no_receiver ?? ''));
        $transactionDate = $complaint->transaction_date ? \Illuminate\Support\Carbon::parse($complaint->transaction_date)->format('d/m/Y') : '—';
        $crimeMediums = collect($complaint->crime_mediums ?: [])->implode(', ');
        $crimeMediums = $crimeMediums ? e($crimeMediums) : '—';
        $platforms = collect($complaint->platforms ?: [])->implode(', ');
        $platforms = $platforms ? e($platforms) : '—';
        $bankSender = $bankSender !== '' ? e($bankSender) : '—';
        $bankReceiver = $bankReceiver !== '' ? e($bankReceiver) : '—';
        $platformProfile = e($complaint->platform_profile_page ?: '—');
        $platformUsername = e($complaint->platform_username ?: '—');
        $platformEmail = e($complaint->platform_email_involved ?: '—');
        $platformMobile = e($complaint->platform_mobile_involved ?: '—');

        $accusedRows = '';
        $accused = $complaint->initial_accused ?: [];
        if (is_array($accused) && count($accused) > 0) {
            foreach ($accused as $index => $a) {
                if (!is_array($a)) {
                    continue;
                }
                $n = $index + 1;
                $aName    = e($a['name'] ?? '—');
                $aFather  = e($a['father_name'] ?? '—');
                $aCnic    = e($a['cnic'] ?? '—');
                $aMobile  = e($a['mobile_no'] ?? '—');
                $aEmail   = e($a['email'] ?? '—');
                $aSocial  = e($a['social_media_url'] ?? '—');
                $aOther   = e($a['other_info'] ?? '—');
                $aDesc    = e($a['description'] ?? '—');
                $aDocs = '';
                $aDocFields = [
                    ['label' => 'CNIC Front', 'value' => $a['cnic_front'] ?? null],
                    ['label' => 'CNIC Back',  'value' => $a['cnic_back'] ?? null],
                    ['label' => 'Photo',      'value' => $a['picture'] ?? null],
                    ['label' => 'Passport',   'value' => $a['passport_attachment'] ?? null],
                ];
                foreach ($aDocFields as $doc) {
                    if (!empty($doc['value'])) {
                        $aDocs .= '<span class="alink"><a href="' . e(url($doc['value'])) . '">' . $doc['label'] . '</a></span>';
                    }
                }
                $accusedRows .= <<<HTML
                <tr>
                  <td class="c">{$n}</td>
                  <td><strong>{$aName}</strong><br/><span class="k" style="font-size:10.5px;">S/O {$aFather}</span></td>
                  <td>{$aCnic}</td>
                  <td>{$aMobile}</td>
                  <td>{$aEmail}</td>
                  <td>{$aDocs}</td>
                </tr>
                <tr class="desc"><td></td><td colspan="5"><span class="k">Other:</span> {$aOther}<br/><span class="k">Social:</span> {$aSocial}<br/><span class="k">Description:</span> {$aDesc}</td></tr>
                HTML;
            }
        } else {
            $accusedRows = '<tr><td colspan="6" class="c muted">No accused recorded at registration</td></tr>';
        }

        $qrHtml = $this->documentQr('complaint_report', (int) $complaint->id, [
            'Type'         => 'Complaint Report',
            'Complaint No' => $number,
            'Complainant'  => $complaint->complainant_name ?: '—',
            'Circle'       => $complaint->circle?->name ?? '—',
            'Status'       => $status,
        ], 72, e($number));

        $body = <<<HTML
        <div class="report">
          <table class="head-table">
            <tr>
              <td class="head-qr">{$qrHtml}</td>
              <td class="head-center">
                <div class="tag">COMPLAINT REPORT</div>
              </td>
              <td class="head-qr"></td>
            </tr>
          </table>

          <div class="meta">
            <div class="mrow"><span class="k">Complaint No:</span><span><strong>{$number}</strong></span></div>
            <div class="mrow"><span class="k">Circle:</span><span>{$circle}</span></div>
            <div class="mrow"><span class="k">Verif. Officer:</span><span><strong>{$voDisplay}</strong></span></div>
            <div class="mrow"><span class="k">Report Date:</span><span>{$reportDate}</span></div>
            <div class="mrow"><span class="k">Status:</span><span>{$status}</span></div>
            <div class="mrow"><span class="k">Diary No:</span><span>{$diary}</span></div>
            <div class="mrow"><span class="k">Operator:</span><span>{$operator}</span></div>
          </div>

          <h3 class="sec">1. Complainant Information</h3>
          <table class="info">
            <tr><td class="k">Name</td><td>{$name}</td><td class="k">Father Name</td><td>{$father}</td></tr>
            <tr><td class="k">CNIC</td><td>{$cnic}</td><td class="k">Gender</td><td>{$gender}</td></tr>
            <tr><td class="k">Contact</td><td>{$phone}</td><td class="k">WhatsApp</td><td>{$whatsapp}</td></tr>
            <tr><td class="k">Email</td><td>{$email}</td><td class="k">Nationality</td><td>{$nationality}</td></tr>
            <tr><td class="k">Passport No</td><td>{$passportNo}</td><td class="k">District</td><td>{$district}</td></tr>
            <tr><td class="k">Address</td><td colspan="3">{$address}</td></tr>
          </table>

          <h3 class="sec">2. Complaint / Offence Details</h3>
          <table class="info">
            <tr><td class="k">Offence Type</td><td>{$offence}</td><td class="k">Priority</td><td>{$priority}</td></tr>
            <tr><td class="k">Source</td><td>{$source}</td><td class="k">Occurrence Date</td><td>{$occurrence}</td></tr>
            <tr><td class="k">Amount Involved</td><td>{$amount}</td><td class="k">Laws</td><td>{$laws}</td></tr>
          </table>
          <div class="desc"><span class="k">Description:</span><div class="descbox">{$description}</div></div>

          <h3 class="sec">3. Identity Files &amp; Attachments</h3>
          <div class="flist-wrap">{$complaintFiles}</div>

          <h3 class="sec">4. Financial Record</h3>
          <table class="info">
            <tr><td class="k">Amount Involved</td><td>{$amount}</td><td class="k">Crime Medium</td><td>{$crimeMediums}</td></tr>
            <tr><td class="k">Sender Bank / A/C</td><td colspan="3">{$bankSender}</td></tr>
            <tr><td class="k">Receiver Bank / A/C</td><td colspan="3">{$bankReceiver}</td></tr>
            <tr><td class="k">Transaction Date</td><td>{$transactionDate}</td><td class="k">Platforms</td><td>{$platforms}</td></tr>
            <tr><td class="k">Platform Profile</td><td colspan="3">{$platformProfile}</td></tr>
            <tr><td class="k">Platform Username</td><td>{$platformUsername}</td><td class="k">Email Involved</td><td>{$platformEmail}</td></tr>
            <tr><td class="k">Mobile Involved</td><td colspan="3">{$platformMobile}</td></tr>
          </table>

          <h3 class="sec">5. Accused (Initial Stage)</h3>
          <table class="accused">
            <thead>
              <tr><th>#</th><th>Name</th><th>CNIC</th><th>Mobile</th><th>Email</th><th>Identity Files</th></tr>
            </thead>
            <tbody>{$accusedRows}</tbody>
          </table>

          <div class="sign-row">
            <div class="sign"><div class="sign-line"></div><div class="small">Operator / Desk — {$operator}</div></div>
            <div class="sign"><div class="sign-line"></div><div class="small">Circle Incharge</div></div>
          </div>

          <div class="foot"><div class="small">Printed: {$issuedAt}</div><div class="tiny">NCCIA — Serving the Nation</div></div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4; margin: 14mm; }
             body { margin:0; font-family: Arial, Helvetica, sans-serif; color:#000; }
             .report { max-width: 175mm; margin: 0 auto; font-size: 13px; line-height: 1.5; }
             .head-table { width:100%; border-collapse:collapse; border-bottom: 3px solid #000; margin-bottom: 10px; }
             .head-table td { border:none; vertical-align:middle; }
             .head-qr { width:90px; }
             .head-center { text-align:center; padding-bottom: 8px; }
             .head-center .logo { width:90px; height:90px; object-fit:contain; border-radius:50%; border:3px solid #264078; padding:3px; background:#fff; }
             .head-center .org { font-size:16px; font-weight:700; margin-top:2px; }
             .head-center .addr { font-size:11px; color:#333; margin-top:2px; }
             .head-center .tag { display:inline-block; margin-top:6px; padding:3px 14px; border:2px solid #000; font-weight:800; font-size:12px; letter-spacing:1.5px; }
             .meta { margin:6px 0; }
             .mrow { margin:2px 0; }
             .mrow .k { display:inline-block; width:110px; font-weight:700; }
             h3.sec { font-size:13px; margin:16px 0 6px; padding:4px 8px; background:#f0f0f0; border-left:5px solid #015C94; }
             table.info { width:100%; border-collapse:collapse; margin-bottom:4px; }
             table.info td { border:1.5px solid #999; padding:5px 8px; vertical-align:top; font-size:12.5px; }
             table.info .k { width:110px; background:#f7f7f7; font-weight:700; }
             .desc { margin-top:6px; }
             .descbox { border:1.5px solid #999; padding:8px; margin-top:4px; font-size:12.5px; white-space:pre-wrap; }
             .flist-wrap { border:1.5px solid #999; padding:8px; }
             .flist { margin:2px 0; }
             table.accused { width:100%; border-collapse:collapse; margin-top:4px; }
             table.accused th { background:#e9e9e9; border:1.5px solid #999; padding:6px 8px; font-size:12px; }
             table.accused td { border:1.5px solid #999; padding:5px 8px; font-size:12.5px; vertical-align:top; }
             table.accused td.c { text-align:center; }
             table.accused tr.desc td { font-size:11.5px; color:#333; }
             .alink { display:inline-block; margin-right:6px; padding:1px 6px; border:1.5px solid #015C94; border-radius:4px; font-size:11px; color:#015C94; }
             .muted { color:#666; }
             .sign-row { width:100%; margin:30px 0 10px; }
             .sign-row .sign { display:inline-block; width:48%; text-align:center; vertical-align:top; }
             .sign-line { border-top:2px solid #000; margin:0 30px 4px; }
             .small { font-size:11px; }
             .tiny { font-size:10px; color:#444; margin-top:2px; }
             .foot { text-align:center; border-top:1px solid #000; margin-top:14px; padding-top:6px; }'
        );
    }

    /**
     * Signed QR block: encodes verify URL + official identifiers.
     */
    protected function documentQr(string $type, int $id, array $fields = [], int $px = 70, string $caption = 'Scan to verify', ?string $noticeToken = null): string
    {
        try {
            $payload = $this->qr->payload($type, $id, $fields, $noticeToken);
            $src = $this->qr->svgDataUri($payload);
            $cap = e($caption);

            return '<div class="nccia-doc-qr" style="text-align:center;line-height:1.15;">'
                . '<img src="' . $src . '" alt="Verify QR" style="width:' . $px . 'px;height:' . $px . 'px;display:block;margin:0 auto;" />'
                . '<div style="font-size:8px;font-weight:700;margin-top:2px;">' . $cap . '</div>'
                . '</div>';
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Resolve uploaded profile signature image HTML.
     */
    protected function getOfficerSignatureHtml(?\App\Models\User $officer, int $maxHeight = 45): string
    {
        $user = $officer ?: request()->user();
        if (!$user || !$user->signature) {
            return '';
        }
        $sigUrl = url('storage/' . ltrim($user->signature, '/'));
        return '<div style="margin-bottom: 4px;"><img src="' . $sigUrl . '" alt="Signature" style="max-height: ' . $maxHeight . 'px; max-width: 140px; object-fit: contain;" /></div>';
    }

    /**
     * Printable Call Up Notice document (Notice For Attendance U/S 160 Cr.PC) with QR verification.
     */
    public function noticeDocument(EnquiryNotice $notice): string
    {
        $notice->loadMissing(['enquiry.complaint.circle', 'enquiry.officer', 'enquiry.accusedPersons', 'enquiry.notices']);

        if (!$notice->verification_token) {
            $notice->verification_token = \Illuminate\Support\Str::random(32);
            $notice->save();
        }

        $enquiry   = $notice->enquiry;
        $complaint = $enquiry?->complaint;
        $circle    = $complaint?->circle;
        $circleName = e($circle?->name ?? 'Lahore');
        $circleCity = e($circle?->city ?? 'Lahore');
        $circleCode = e($circle?->code ?? 'LHR');

        $logo = url('images/images.jpg');

        $enquiryNo = e($enquiry?->enquiry_number ?: ($complaint?->tracking_no ?: ('ENQ-CCRC-' . $circleCode . '-' . $enquiry?->id)));
        $qrHtml = $this->documentQr('notice', (int) $notice->id, [
            'Type'        => 'Summon / Notice',
            'Summon No'   => $notice->notice_number ?: ('N-' . $notice->id),
            'Enquiry No'  => $enquiry?->enquiry_number ?: ('#' . $enquiry?->id),
            'Receiver'    => $notice->receiver_name ?: '—',
        ], 70, 'Scan to verify', $notice->verification_token);
        $regDate   = $enquiry?->reg_date ? $enquiry->reg_date->format('d-m-Y') : ($complaint?->report_date ? \Carbon\Carbon::parse($complaint->report_date)->format('d-m-Y') : date('d-m-Y'));
        $complainantName = e($complaint?->complainant_name ?: ($enquiry?->direct_info['complainant_name'] ?? '—'));
        
        $receiverName = e($notice->receiver_name ?: '—');
        
        $personFatherName = $notice->father_name;
        $personCnic       = $notice->cnic;
        $personPhone      = $notice->phone;
        $personAddress    = $notice->address;

        // If father_name / details are empty on notice record, resolve from matching accused / witness / complainant
        if (empty($personFatherName) || empty($personCnic) || empty($personAddress) || empty($personPhone)) {
            if ($enquiry && $notice->person_type === 'accused') {
                $matchedAccused = $enquiry->accusedPersons->first(fn ($a) => trim(strtolower($a->name ?? '')) === trim(strtolower($notice->receiver_name ?? '')))
                    ?: $enquiry->accusedPersons->first();
                if ($matchedAccused) {
                    $personFatherName = $personFatherName ?: $matchedAccused->father_name;
                    $personCnic       = $personCnic ?: $matchedAccused->cnic;
                    $personPhone      = $personPhone ?: ($matchedAccused->contact_no ?: $matchedAccused->whatsapp_no);
                    $personAddress    = $personAddress ?: ($matchedAccused->postal_address ?: $matchedAccused->permanent_address);
                }
            } elseif ($enquiry && $notice->person_type === 'witness') {
                $matchedWitness = $enquiry->witnesses->first(fn ($w) => trim(strtolower($w->name ?? '')) === trim(strtolower($notice->receiver_name ?? '')))
                    ?: $enquiry->witnesses->first();
                if ($matchedWitness) {
                    $personFatherName = $personFatherName ?: $matchedWitness->father_name;
                    $personCnic       = $personCnic ?: $matchedWitness->cnic;
                    $personPhone      = $personPhone ?: ($matchedWitness->contact_no ?: $matchedWitness->whatsapp_no);
                    $personAddress    = $personAddress ?: ($matchedWitness->address ?: ($matchedWitness->mailing_address ?: $matchedWitness->permanent_address));
                }
            } elseif ($notice->person_type === 'complainant') {
                $personFatherName = $personFatherName ?: ($complaint?->father_name ?: ($enquiry?->direct_info['father_name'] ?? ''));
                $personCnic       = $personCnic ?: ($complaint?->cnic ?: ($enquiry?->direct_info['cnic'] ?? ''));
                $personPhone      = $personPhone ?: ($complaint?->contact_no ?: ($enquiry?->direct_info['contact_no'] ?? ''));
                $personAddress    = $personAddress ?: ($complaint?->address ?: ($enquiry?->direct_info['address'] ?? ''));
            } else {
                // If person_type is unset, check if receiver matches an accused
                $matchedAccused = $enquiry?->accusedPersons?->first(fn ($a) => trim(strtolower($a->name ?? '')) === trim(strtolower($notice->receiver_name ?? '')));
                if ($matchedAccused) {
                    $personFatherName = $personFatherName ?: $matchedAccused->father_name;
                    $personCnic       = $personCnic ?: $matchedAccused->cnic;
                    $personPhone      = $personPhone ?: ($matchedAccused->contact_no ?: $matchedAccused->whatsapp_no);
                    $personAddress    = $personAddress ?: ($matchedAccused->postal_address ?: $matchedAccused->permanent_address);
                }
            }
        }

        $fatherName   = e($personFatherName ?: '');
        $parentageStr = $fatherName ? ' S/O ' . $fatherName : '';
        $cnic         = e($personCnic ?: '0000000000000');
        $address      = e($personAddress ?: 'Cyber Crime Reporting Center Area');
        $phone        = e($personPhone ?: '—');
        
        $noticeDate   = $notice->notice_date ? $notice->notice_date->format('d-m-Y') : date('d-m-Y');
        
        // Appearance Date & Time
        $appDate = $notice->appearance_date ? \Carbon\Carbon::parse($notice->appearance_date)->format('d-m-Y') : date('d-m-Y', strtotime('+1 day'));
        $appTime = $notice->appearance_date ? \Carbon\Carbon::parse($notice->appearance_date)->format('H:i') : ($notice->appearance_time ?: '11:00');
        
        // Gist of Allegation
        $gistOfAllegation = e($notice->description ?: ($enquiry?->charge_against ?: ($complaint?->offence_type ?: ($complaint?->description ? \Illuminate\Support\Str::limit($complaint->description, 120) : 'Financial Fraud / Cyber Crime Allegation'))));

        // Complainant Details
        $compName       = e($complaint?->complainant_name ?: ($enquiry?->direct_info['complainant_name'] ?? '—'));
        $compFatherName = e($complaint?->father_name ?: ($enquiry?->direct_info['father_name'] ?? ''));
        $compParentage  = $compFatherName ? ' S/O ' . $compFatherName : '';
        $compCnic       = e($complaint?->cnic ?: ($enquiry?->direct_info['cnic'] ?? '—'));
        $compPhone      = e($complaint?->contact_no ?: ($enquiry?->direct_info['contact_no'] ?? '—'));
        $compAddress    = e($complaint?->address ?: ($enquiry?->direct_info['address'] ?? '—'));

        // Officer Info & Profile Signature
        $officer      = $enquiry?->officer ?: request()->user();
        $officerName  = e($officer?->name ?: 'NABEEL HUSSAIN');
        $officerDesig = e($officer?->designation ?: 'Sub Inspector');
        $officerSigHtml = $this->getOfficerSignatureHtml($officer);

        // Address of reporting center (phone removed)
        $stationAddress = 'Cyber Crime Reporting Center, ' . $circleName . ', Police Station, National Cybercrime Investigation Agency (NCCIA), Street No 15, Wafaqi Colony, Canal Road ' . $circleCity . '.';

        // Multi-notice history tracking (Notice 1, 2, 3)
        $allNotices = $enquiry ? $enquiry->notices()->orderBy('id')->get() : collect([$notice]);
        $noticeIndex = 0;
        foreach ($allNotices as $idx => $n) {
            if ($n->id === $notice->id) {
                $noticeIndex = $idx;
                break;
            }
        }
        $seq = (int) ($notice->sequence_no ?: ($noticeIndex + 1));
        if ($seq <= 0) {
            $seq = 1;
        }
        $suffix = match ($seq % 100) {
            11, 12, 13 => 'th',
            default => match ($seq % 10) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th',
            },
        };
        $noticeLabel = $seq . $suffix . ' Notice';

        // Build Notice History Box for 2nd, 3rd, 4th Notices
        $historyHtml = '';
        if ($seq >= 2 && $allNotices->count() > 0) {
            $historyRows = '';
            for ($i = 0; $i < min($seq - 1, $allNotices->count()); $i++) {
                $prev = $allNotices[$i];
                $prevIndex = $i + 1;
                $pSuffix = match ($prevIndex % 100) {
                    11, 12, 13 => 'th',
                    default => match ($prevIndex % 10) {
                        1 => 'st',
                        2 => 'nd',
                        3 => 'rd',
                        default => 'th',
                    },
                };
                $prevNum = $prevIndex . $pSuffix . ' Notice';
                $pDate = $prev->notice_date ? $prev->notice_date->format('d-m-Y') : '—';
                $pAppDate = $prev->appearance_date ? \Carbon\Carbon::parse($prev->appearance_date)->format('d-m-Y') : '—';
                
                $pStatus = 'Not Appeared';
                $statusColor = '#dc2626';
                if ($prev->status === 'appeared' || $prev->appeared_at || $prev->appearance_remarks === 'appeared') {
                    $pStatus = 'Appeared';
                    $statusColor = '#16a34a';
                } elseif ($prev->status === 'served') {
                    $pStatus = 'Served (Non-Appearance)';
                }

                $historyRows .= <<<HTML
                <tr>
                  <td style="padding: 4px 8px; border: 1px solid #cbd5e1; font-weight: 700;">{$prevNum}</td>
                  <td style="padding: 4px 8px; border: 1px solid #cbd5e1;">{$pDate}</td>
                  <td style="padding: 4px 8px; border: 1px solid #cbd5e1;">{$pAppDate}</td>
                  <td style="padding: 4px 8px; border: 1px solid #cbd5e1; color: {$statusColor}; font-weight: 700;">{$pStatus}</td>
                </tr>
                HTML;
            }

            $historyHtml = <<<HTML
            <div style="margin: 14px 0 10px 0; border: 1.5px solid #1e293b; padding: 8px 10px; background: #f8fafc; border-radius: 4px;">
              <div style="font-weight: 800; font-size: 11px; color: #0f172a; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;">
                PREVIOUS CALL UP NOTICE RECORD / ATTENDANCE STATUS:
              </div>
              <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                <thead>
                  <tr style="background: #e2e8f0;">
                    <th style="padding: 4px 8px; border: 1px solid #cbd5e1; text-align: left;">Notice No.</th>
                    <th style="padding: 4px 8px; border: 1px solid #cbd5e1; text-align: left;">Issue Date</th>
                    <th style="padding: 4px 8px; border: 1px solid #cbd5e1; text-align: left;">Appearance Date</th>
                    <th style="padding: 4px 8px; border: 1px solid #cbd5e1; text-align: left;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {$historyRows}
                </tbody>
              </table>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="callup-notice">
          <table class="notice-top-table">
            <tr>
              <td class="top-logo-cell"></td>
              <td class="notice-center-cell">
                <div class="center-name">Cyber Crime Reporting Center, {$circleName}</div>
                <div class="station-addr">{$stationAddress}</div>
                <div class="notice-main-heading">NOTICE FOR ATTENDANCE U/S 160 Cr.PC</div>
              </td>
              <td class="top-qr-cell">
                {$qrHtml}
              </td>
            </tr>
          </table>

          <div class="notice-meta-row">
            <div class="notice-no"><strong>{$noticeLabel}</strong></div>
            <div class="notice-date"><strong>Dated:</strong>{$noticeDate}</div>
          </div>

          <div style="margin: 8px 0 12px 0; border: 1.5px solid #1e293b; background: #ffffff; border-radius: 4px; overflow: hidden;">
            <div style="background: #e2e8f0; font-weight: 800; font-size: 11.5px; color: #0f172a; padding: 4px 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #cbd5e1;">
              To (Notice Recipient):
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
              <tr>
                <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; width: 85px; font-weight: 700; background: #f8fafc; color: #1e293b;">Name</td>
                <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; font-size: 13px;"><strong>{$receiverName}{$parentageStr}</strong></td>
              </tr>
              <tr>
                <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; font-weight: 700; background: #f8fafc; color: #1e293b;">CNIC</td>
                <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1;">{$cnic}</td>
              </tr>
              <tr>
                <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; font-weight: 700; background: #f8fafc; color: #1e293b;">Contact</td>
                <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1;">{$phone}</td>
              </tr>
              <tr>
                <td style="padding: 4px 10px; font-weight: 700; background: #f8fafc; color: #1e293b;">Address</td>
                <td style="padding: 4px 10px;">{$address}</td>
              </tr>
            </table>
          </div>

          <div class="subject-line">
            <strong>SUBJECT: 160 Cr.PC Enquiry NO. {$enquiryNo} OF Cyber Crime Reporting Center, {$circleName}</strong>
          </div>

          <div class="notice-body-text">
            <p style="margin: 6px 0;">
              NCCIA, Cyber Crime Reporting Center, {$circleName}, is conducting a probe on the above titled Enquiry. The details are mentioned below:
            </p>
            <div style="margin: 8px 0 10px 0; border: 1.5px solid #1e293b; background: #ffffff; border-radius: 4px; overflow: hidden;">
              <div style="background: #e2e8f0; font-weight: 800; font-size: 11.5px; color: #0f172a; padding: 4px 10px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #cbd5e1;">
                Enquiry Information:
              </div>
              <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <tr>
                  <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; width: 170px; font-weight: 700; background: #f8fafc; color: #1e293b;">Enquiry No.</td>
                  <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; font-weight: 700;">{$enquiryNo}</td>
                </tr>
                <tr>
                  <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; font-weight: 700; background: #f8fafc; color: #1e293b;">Enquiry Registration Date</td>
                  <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1;">{$regDate}</td>
                </tr>
                <tr>
                  <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1; font-weight: 700; background: #f8fafc; color: #1e293b;">Complainant Name</td>
                  <td style="padding: 4px 10px; border-bottom: 1px solid #cbd5e1;">{$compName}</td>
                </tr>
                <tr>
                  <td style="padding: 4px 10px; font-weight: 700; background: #f8fafc; color: #1e293b;">Gist of Allegation</td>
                  <td style="padding: 4px 10px;">{$gistOfAllegation}</td>
                </tr>
              </table>
            </div>

            <p style="margin: 12px 0 6px 0; text-align: justify; line-height: 1.6;">
              Therefore, you are directed to appear in person before the undersigned to record your version, on date: <strong>{$appDate}</strong> time <strong>{$appTime}</strong> at the following address <strong>{$stationAddress}</strong>
            </p>
            <p style="margin: 8px 0; text-align: justify;">
              In case of non-appearance, it will be assumed that you have nothing to present or state in your defense.
            </p>

            {$historyHtml}
          </div>

          <div class="officer-sign-block">
            {$officerSigHtml}
            <div class="off-name"><strong>{$officerName}</strong></div>
            <div class="off-desig">{$officerDesig}</div>
            <div class="off-branch">Cyber Crime Reporting Center, {$circleName}</div>
          </div>

          <div class="notice-warning-footer">
            (Non-compliance to said notice is punishable under sec 174 PPC 1860)
          </div>
        </div>
        HTML;
    }

    public function noticePrintDocument(EnquiryNotice $notice): string
    {
        return $this->document(
            $this->noticeDocument($notice),
            '@page { size: A4 portrait; margin: 12mm 14mm; }
             body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; }
             .callup-notice { width: 100%; max-width: 175mm; margin: 0 auto; font-size: 13px; line-height: 1.45; }
             .notice-top-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
             .notice-top-table td { vertical-align: top; padding: 0; }
             .top-logo-cell { width: 75px; text-align: left; }
             .notice-logo { width: 70px; height: 70px; object-fit: contain; }
             .notice-center-cell { text-align: center; padding: 0 10px; }
             .agency-title { font-size: 15px; font-weight: 800; letter-spacing: 0.5px; color: #000; text-transform: uppercase; margin-bottom: 2px; }
             .center-name { font-size: 13px; font-weight: 700; color: #111; margin-bottom: 2px; }
             .station-addr { font-size: 10px; color: #333; line-height: 1.3; margin-bottom: 6px; }
             .notice-main-heading { font-size: 14px; font-weight: 800; text-transform: uppercase; text-decoration: underline; letter-spacing: 0.5px; margin-top: 4px; }
             .top-qr-cell { width: 75px; text-align: right; }
             .notice-qr { width: 70px; height: 70px; }
             .qr-label { font-size: 9px; text-align: center; font-weight: 700; margin-top: 2px; }
             .notice-meta-row { display: flex; justify-content: space-between; width: 100%; border-bottom: 1.5px solid #000; padding-bottom: 4px; margin: 10px 0 14px 0; font-size: 13px; }
             .to-block { margin-bottom: 14px; font-size: 13px; line-height: 1.4; }
             .to-name { font-size: 13.5px; margin: 2px 0; }
             .subject-line { font-size: 13px; font-weight: 800; margin: 14px 0 10px 0; text-transform: uppercase; border-bottom: 1px solid #bbb; padding-bottom: 4px; }
             .enq-details-box { margin: 8px 0; line-height: 1.6; }
             .officer-sign-block { margin-top: 40px; margin-left: auto; width: 260px; text-align: left; font-size: 13px; }
             .off-name { font-size: 13.5px; text-transform: uppercase; }
             .notice-warning-footer { margin-top: 40px; text-align: center; font-size: 11.5px; font-weight: 700; color: #111; }'
        );
    }

    /**
     * Printable Confidential Final Report (CFR) matching PDF Page 1 layout.
     */
    public function cfrPrintDocument(Enquiry $enquiry): string
    {
        $enquiry->loadMissing(['complaint.circle', 'officer', 'accusedPersons']);

        $circle     = $enquiry->complaint?->circle;
        $circleName = e($circle?->name ?? 'LAHORE');
        $circleCode = e($circle?->code ?? 'LHR');
        $enquiryNo  = e($enquiry->enquiry_number ?: ($enquiry->complaint?->tracking_no ?: ('ENQ-' . $enquiry->id)));
        $cfrDate    = $enquiry->cfr_date ? \Carbon\Carbon::parse($enquiry->cfr_date)->format('d-m-Y') : date('d-m-Y');

        // 3: Complainant details
        $directInfo  = is_array($enquiry->direct_info) ? $enquiry->direct_info : [];
        $compName    = e($enquiry->complaint?->complainant_name ?: ($directInfo['complainant_name'] ?? '—'));
        $compFather  = e($enquiry->complaint?->father_name ?: '—');
        $compAddr    = e($enquiry->complaint?->address ?: '—');
        $compPhone   = e($enquiry->complaint?->contact_no ?: '—');
        $compStr     = "<strong>{$compName}</strong> S/O {$compFather}, Permanent/Temporary Address: {$compAddr}, Phone: {$compPhone}";

        // 4: Accused details
        $accusedList = $enquiry->accusedPersons;
        $accStr = '';
        if ($accusedList && $accusedList->count() > 0) {
            foreach ($accusedList as $i => $acc) {
                $aNum = $i + 1;
                $aName = e($acc->name ?: '—');
                $aFather = e($acc->father_name ?: '—');
                $aCnic = e($acc->cnic ?: '—');
                $aAddr = e($acc->address ?: '—');
                $accStr .= "<div><strong>{$aNum}. {$aName}</strong> S/O {$aFather}, CNIC: {$aCnic}, Address: {$aAddr}</div>";
            }
        } else {
            $initialAcc = $enquiry->complaint?->initial_accused;
            if (is_array($initialAcc) && count($initialAcc) > 0) {
                foreach ($initialAcc as $i => $acc) {
                    $aNum = $i + 1;
                    $accArray = is_array($acc) ? $acc : [];
                    $aName = e($accArray['name'] ?? '—');
                    $aFather = e($accArray['father_name'] ?? '—');
                    $aCnic = e($accArray['cnic'] ?? '—');
                    $aAddr = e($accArray['other_info'] ?? '—');
                    $accStr .= "<div><strong>{$aNum}. {$aName}</strong> S/O {$aFather}, CNIC: {$aCnic}, Address: {$aAddr}</div>";
                }
            } else {
                $accStr = '<div>Unknown / To be traced</div>';
            }
        }

        // 5: Status of alleged (service / private)
        $serviceStatus = 'Private person(s) / Not in government service.';

        // 6: Brief allegation
        $briefAllegation = nl2br(e($enquiry->brief_allegation ?: ($enquiry->complaint?->description ?: ($enquiry->cfr_summary ?: 'The complainant alleged fraudulent and cyber offense activities.'))));

        // 7: Charge against alleged
        $chargeAgainst = nl2br(e($enquiry->charge_against ?: 'Offence under Prevention of Electronic Crimes Act (PECA) 2016.'));

        // 8: Oral evidence
        $oralEvidence = nl2br(e($enquiry->oral_evidence ?: 'Statements of complainant and witnesses recorded during enquiry proceedings.'));

        // 9: Documentary evidence
        $docEvidence = nl2br(e($enquiry->documentary_evidence ?: 'Forensic data extraction reports, bank statement records, Call Detail Records (CDR), and identity documents.'));

        // 10: Conclusion of Enquiry Officer
        $conclusion = nl2br(e($enquiry->conclusion ?: ($enquiry->cfr_summary ?: 'Based on the oral and documentary evidence gathered, the allegations stand substantiated against the accused person(s). Regular Case/FIR is recommended for registration.')));

        // Officer Info
        $officer      = $enquiry->officer ?: request()->user();
        $officerName  = e($officer?->name ?: 'Sub Inspector');
        $officerDesig = e($officer?->designation ?: 'Sub Inspector');
        $officerSigHtml = $this->getOfficerSignatureHtml($officer);
        $qrHtml = $this->documentQr('cfr', (int) $enquiry->id, [
            'Type'        => 'Confidential Final Report',
            'Enquiry No'  => $enquiry->enquiry_number ?: ('ENQ-' . $enquiry->id),
            'Complainant' => $enquiry->complaint?->complainant_name ?: '—',
            'Officer'     => $officer?->name ?: '—',
            'Date'        => $cfrDate,
        ], 68, e($enquiryNo));

        $body = <<<HTML
        <div class="cfr-doc">
          <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
            <tr>
              <td style="width:80px;border:none;vertical-align:top;">{$qrHtml}</td>
              <td style="border:none;text-align:center;vertical-align:middle;">
                <div class="cfr-title">CONFIDENTIAL FINAL REPORT</div>
              </td>
              <td style="width:80px;border:none;"></td>
            </tr>
          </table>

          <table class="cfr-table">
            <tr>
              <td class="col-num">1</td>
              <td class="col-content">
                <strong>NCCIA (National Cyber Crime Investigation Agency) CIRCLE, {$circleName}.</strong>
              </td>
            </tr>
            <tr>
              <td class="col-num">2</td>
              <td class="col-content">
                <table style="width: 100%; border-collapse: collapse;">
                  <tr>
                    <td style="width: 50%; font-weight: 700; border: none; padding: 0;">ENQUIRY NO. {$enquiryNo}</td>
                    <td style="width: 50%; font-weight: 700; border: none; padding: 0; text-align: right;">DATED: {$cfrDate}</td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="col-num">3</td>
              <td class="col-content">
                <div class="cfr-heading">NAME OF COMPLAINANT WITH PARENTAGE, PERMANENT/ TEMPORARY ADDRESS, PH.</div>
                <div class="cfr-val">{$compStr}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">4</td>
              <td class="col-content">
                <div class="cfr-heading">NAME OF ALLEGED/ EACH ALLEGED WITH PARENTAGE, CNIC NO. AND PERMANENT/ TEMPORARY ADDRESS.</div>
                <div class="cfr-val">{$accStr}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">5</td>
              <td class="col-content">
                <div class="cfr-heading">PRESENT STATUS OF EACH ALLEGED WHETHER IN SERVICE OR NOT.</div>
                <div class="cfr-val">{$serviceStatus}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">6</td>
              <td class="col-content">
                <div class="cfr-heading">BRIEF ALLEGATION</div>
                <div class="cfr-val">{$briefAllegation}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">7</td>
              <td class="col-content">
                <div class="cfr-heading">CHARGE AGAINST ALLEGED.</div>
                <div class="cfr-val">{$chargeAgainst}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">8</td>
              <td class="col-content">
                <div class="cfr-heading">ORAL EVIDENCE AGAINST ALLEGED.</div>
                <div class="cfr-val">{$oralEvidence}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">9</td>
              <td class="col-content">
                <div class="cfr-heading">DOCUMENTARY EVIDENCE AGAINST ALLEGED.</div>
                <div class="cfr-val">{$docEvidence}</div>
              </td>
            </tr>
            <tr>
              <td class="col-num">10</td>
              <td class="col-content">
                <div class="cfr-heading">CONCLUSION OF ENQUIRY OFFICER WITH CONVINCING REASONS AGAINST ALLEGED.</div>
                <div class="cfr-val">{$conclusion}</div>
              </td>
            </tr>
          </table>

          <div class="cfr-submit-note">
            CFR is submitted for your kind perusal please.
          </div>

          <div class="cfr-sign-block">
            {$officerSigHtml}
            <strong>{$officerName}</strong><br/>
            {$officerDesig}<br/>
            <strong>NCCIA/CCRC/{$circleCode}</strong>
          </div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4 portrait; margin: 12mm 14mm; }
             body { margin:0; padding:0; font-family: "Times New Roman", Times, serif, Arial; color:#000; background:#fff; }
             .cfr-doc { width: 100%; max-width: 175mm; margin: 0 auto; font-size: 12.5px; line-height: 1.45; }
             .cfr-title { text-align: center; font-size: 16px; font-weight: 800; text-decoration: underline; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.8px; }
             .cfr-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
             .cfr-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; }
             .col-num { width: 30px; text-align: center; font-weight: 800; font-size: 12.5px; }
             .col-content { font-size: 12.5px; }
             .cfr-heading { font-weight: 800; text-transform: uppercase; font-size: 11.5px; margin-bottom: 3px; }
             .cfr-val { font-size: 12.5px; line-height: 1.4; }
             .cfr-submit-note { margin-top: 14px; font-size: 11.5px; font-style: italic; }
             .cfr-sign-block { margin-top: 30px; margin-left: auto; width: 220px; text-align: center; font-size: 13px; line-height: 1.4; }'
        );
    }

    /**
     * Printable Request for Providing Forensic Analysis Report matching PDF Page 3.
     */
    public function forensicRequestPrintDocument(Enquiry $enquiry, array $devices = [], string $analysisScope = ''): string
    {
        $enquiry->loadMissing(['complaint.circle', 'officer', 'accusedPersons']);

        $circle     = $enquiry->complaint?->circle;
        $circleCity = e($circle?->city ?: ($circle?->name ?: 'LAHORE'));
        $cleanCity  = strtoupper(trim(str_ireplace(['circle', 'zone', 'nccia-rc', 'nccia', '-'], '', $circleCity))) ?: 'LAHORE';
        $circleName = e($circle?->name ?? 'LAHORE');
        $zoneName   = "NCCIA - ZONE {$cleanCity}";
        $rcName     = "NCCIA-RC {$cleanCity}";
        $enquiryNo  = e($enquiry->enquiry_number ?: ($enquiry->complaint?->tracking_no ?: ('ENQ-' . $enquiry->id)));
        $enqRegDate   = $enquiry->reg_date ? \Carbon\Carbon::parse($enquiry->reg_date)->format('d/m/Y') : ($enquiry->created_at ? $enquiry->created_at->format('d/m/Y') : '');
        $enquiryNoDisplay = $enqRegDate ? "{$enquiryNo} dated {$enqRegDate}" : $enquiryNo;
        
        $isFirCase      = !empty($enquiry->case_number) || ($enquiry->complaint?->case_type === 'fir') || str_starts_with(strtoupper($enquiryNo), 'FIR') || str_starts_with(strtoupper($enquiryNo), 'CASE');
        $caseTypeLabel  = $isFirCase ? 'CASE FIR' : 'ENQUIRY';
        $caseTypeFull   = $isFirCase ? "CASE FIR NO. {$enquiryNoDisplay}" : "ENQUIRY NO. {$enquiryNoDisplay}";

        // Accused List
        $accList = $enquiry->accusedPersons;
        
        // Filter accused based on devices owners
        if (count($devices) > 0) {
            $deviceOwners = array_map(function($d) { return trim(strtolower($d['owner'] ?? '')); }, $devices);
            $deviceOwners = array_filter($deviceOwners);
            
            if (!empty($deviceOwners) && $accList && $accList->count() > 0) {
                $filtered = $accList->filter(function($a) use ($deviceOwners) {
                    return in_array(strtolower(trim($a->name)), $deviceOwners);
                });
                if ($filtered->count() > 0) {
                    $accList = $filtered->values();
                }
            }
        }

        $accRows = '';
        if ($accList && $accList->count() > 0) {
            foreach ($accList as $i => $acc) {
                $n = $i + 1;
                $accRows .= "<div><strong>{$n}. " . e($acc->name) . '</strong> S/O ' . e($acc->father_name ?: '—') . ' R/O ' . e($acc->address ?: '—') . '</div>';
            }
        } else {
            $accRows = '<div>1. Name S/o R/o ________________________________________________</div>';
        }

        $briefContents = e($enquiry->charge_against ?: ($enquiry->complaint?->description ? \Illuminate\Support\Str::limit($enquiry->complaint->description, 160) : 'alleged cybercrime offences.'));

        // Seized Devices Table
        $deviceRows = '';
        $accusedArr = $enquiry->accusedPersons ? $enquiry->accusedPersons->toArray() : ($enquiry->accused_persons ?: []);
        if (count($devices) > 0) {
            foreach ($devices as $i => $dev) {
                $n = $i + 1;
                $devType = e($dev['type'] ?? 'Mobile Phone');
                $devModel = e($dev['model'] ?? '—');
                
                $devOwner = e($dev['owner'] ?? ($dev['seized_from'] ?? ''));
                if (empty($devOwner) && !empty($accusedArr)) {
                    $devOwner = e($accusedArr[$i]['name'] ?? ($accusedArr[0]['name'] ?? '—'));
                }
                if (empty($devOwner)) {
                    $devOwner = '—';
                }
                
                $imeiText = trim(e($dev['imei'] ?? ''));
                $imei2Text = trim(e($dev['imei2'] ?? ''));
                $serialText = trim(e($dev['serial_no'] ?? ''));
                
                $identifiers = [];
                if ($imeiText && $imei2Text) {
                    $identifiers[] = 'IMEI 1: ' . $imeiText;
                    $identifiers[] = 'IMEI 2: ' . $imei2Text;
                } elseif ($imeiText || $imei2Text) {
                    $identifiers[] = 'IMEI: ' . ($imeiText ?: $imei2Text);
                }
                if ($serialText) {
                    $identifiers[] = 'SN: ' . $serialText;
                }
                $devImei = empty($identifiers) ? '—' : implode('<br/>', $identifiers);
                
                $devStorage = e($dev['storage_capacity'] ?? '—');
                $deviceRows .= "<tr><td style=\"text-align:center;\">{$n}</td><td><strong>{$devOwner}</strong></td><td>{$devType}</td><td>{$devModel}</td><td>{$devImei}</td><td>{$devStorage}</td></tr>";
            }
        } else {
            $firstAcc = !empty($accusedArr) ? e($accusedArr[0]['name'] ?? 'Accused') : '—';
            $deviceRows = <<<HTML
            <tr><td style="text-align:center; height:24px;">1</td><td><strong>{$firstAcc}</strong></td><td>Mobile Phone / Smartphone</td><td>Apple / Samsung</td><td>IMEI1: 358900000000000</td><td>256GB</td></tr>
            <tr><td style="text-align:center; height:24px;">2</td><td><strong>{$firstAcc}</strong></td><td>SIM Card / Memory Card</td><td>SanDisk 64GB</td><td>N/A</td><td>64GB</td></tr>
            <tr><td style="text-align:center; height:24px;">3</td><td><strong>{$firstAcc}</strong></td><td>Laptop / Hard Drive</td><td>Dell Inspiron</td><td>SN: 4598000</td><td>1TB</td></tr>
            HTML;
        }

        $officer      = $enquiry->officer ?: request()->user();
        $officerName  = e($officer?->name ?: 'Investigation Officer');
        $officerDesig = e($officer?->designation ?: 'Investigation Officer');
        $officerPhone = e($officer?->phone ?: ($officer?->contact_no ?: ''));
        $officerSigHtml = $this->getOfficerSignatureHtml($officer);
        $dateTimeStr  = now()->format('d/m/Y h:i A');
        $qrHtml = $this->documentQr('scope', (int) $enquiry->id, [
            'Type'        => 'Forensic Scope Letter',
            'Enquiry No'  => $enquiry->enquiry_number ?: ('ENQ-' . $enquiry->id),
            'Complainant' => $enquiry->complaint?->complainant_name ?: '—',
            'Officer'     => $officer?->name ?: '—',
            'Circle'      => $circle?->name ?? '—',
        ], 64, $enquiryNo);

        $analysisScopeHtml = $analysisScope ? nl2br(e($analysisScope)) : "Conduct forensic examination and data extraction of the devices to identify the Facebook IDs, communication chats, emails, and media corresponding to the attached links and images.<br/><br/><strong>Social Media Profile URL:</strong><br/>1. Facebook: ________________________________________________<br/>2. Instagram: ________________________________________________<br/>3. Twitter: __________________________________________________";

        $body = <<<HTML
        <div class="forensic-req-doc">
          <table style="width:100%;border-collapse:collapse;border-bottom:2px solid #000;margin-bottom:12px;">
            <tr>
              <td style="width:80px;border:none;vertical-align:middle;">{$qrHtml}</td>
              <td style="border:none;text-align:center;vertical-align:middle;">
                <div style="font-size:12px;font-weight:700;">Forensic Lab Examination Request</div>
              </td>
              <td style="width:80px;border:none;"></td>
            </tr>
          </table>
          <div class="to-header">
            <strong>THE INCHARGE</strong><br/>
            <strong>NCCIA, CCRC, {$circleName}.</strong>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 8px;">
            <div><strong>{$caseTypeLabel} No:</strong> {$enquiryNoDisplay}</div>
            <div><strong>Dated:</strong> {$dateTimeStr}</div>
          </div>

          <div class="subject-block">
            <strong>SUBJECT: REQUEST FOR PROVIDING FORENSIC ANALYSIS REPORT IN {$caseTypeFull} OF PS. NCCIA, CCRC, {$circleName}.</strong>
          </div>

          <div class="salutation"><strong>SIR,</strong></div>

          <div class="req-body">
            <p><strong>BRIEF:</strong> THE SUBJECT CASE HAS BEEN REGISTERED AT CCRC {$circleName}, NCCIA IN {$caseTypeLabel} NO. <strong>{$enquiryNoDisplay}</strong>, AND THE BELOW EVIDENTIARY MEDIA REQUIRES FORENSIC ANALYSIS TO CONCLUDE THE INVESTIGATION ON MERIT. THE SUBJECT-CITED {$caseTypeLabel} HAS BEEN REGISTERED AGAINST THE ACCUSED PERSON,</p>
            <div class="accused-list" style="margin: 6px 0 10px 16px;">
              {$accRows}
            </div>

            <p>THE BRIEF CONTENTS OF THE CASE ARE THAT THE ALLEGED PERSON IS INVOLVED IN <strong>{$briefContents}</strong></p>

            <p>DURING THE COURSE OF ENQUIRY/INVESTIGATION, THE RELEVANT EVIDENTIARY MEDIA WAS TAKEN INTO POSSESSION FOR FORENSIC EXAMINATION. THE DETAIL OF THE EVIDENTIARY MEDIA IS AS UNDER:</p>

            <div class="sec-title">EVIDENTIARY MEDIA RECOVERED</div>
            <table class="forensic-table">
              <thead>
                <tr>
                  <th style="width: 40px;">SR. NO.</th>
                  <th>RECOVERED / SEIZED FROM</th>
                  <th>TYPE OF EVIDENTIARY DEVICE</th>
                  <th>MAKE / MODEL</th>
                  <th>IMEI / SERIAL NO</th>
                  <th>STORAGE CAPACITY</th>
                </tr>
              </thead>
              <tbody>
                {$deviceRows}
              </tbody>
            </table>

            <div class="sec-title" style="margin-top: 14px;">SCOPE FOR FORENSIC ANALYSIS</div>
            <p><strong>YOU ARE REQUESTED TO CONDUCT FORENSIC EXAMINATION AND PROVIDE REPORT ON THE FOLLOWING SCOPE:</strong></p>
            <p style="margin: 4px 0;">{$analysisScopeHtml}</p>

            <p style="margin-top: 10px;">
              It is therefore requested that the allied forensic analysis report, as per the above scope, may kindly be furnished at the earliest to enable the undersigned to finalize the instant case/enquiry on merit, please.
            </p>

            <div class="enclosures-block" style="margin-top: 14px;">
              <strong>ENCLOSURES:</strong><br/>
              1. COPY OF {$caseTypeLabel} NO. <strong>{$enquiryNoDisplay}</strong><br/>
              2. COPY OF SEIZURE MEMO (RECOVERY MEMO) OF DIGITAL DEVICE
            </div>
          </div>

          <div class="req-sign-block">
            {$officerSigHtml}
            <strong>{$officerName}</strong><br/>
            {$officerDesig}<br/>
HTML;
        if ($officerPhone) {
            $body .= "<span>Contact: <strong>{$officerPhone}</strong></span><br/>";
        }
        $body .= <<<HTML
            National Cyber Crime Investigation Agency<br/>
            <strong>{$rcName}</strong>
          </div>

          <!-- ── 1. Endorsement: Circle Incharge to DD Forensic ── -->
          <div style="margin-top: 22px; border-top: 1.5px dashed #000; padding-top: 12px; font-size: 12px;">
            <table style="width: 100%; border: none; margin: 0; font-size: 12px;">
              <tr>
                <td style="border: none; padding: 0; vertical-align: top; width: 60%;">
                  <strong>To,</strong><br/>
                  <strong>Deputy Director (Forensics),</strong><br/>
                  National Cyber Crime Investigation Agency<br/>
                  {$zoneName}
                </td>
                <td style="border: none; padding: 0; vertical-align: top; text-align: right; width: 40%;">
                  <strong>Dated:</strong> ____________________
                </td>
              </tr>
            </table>
            <div style="margin: 8px 0;">
              <strong>Remarks / Order:</strong>
              <div style="margin-top: 3px; padding: 4px 8px; border-bottom: 1px dotted #555; min-height: 20px; font-style: italic;">
                Approved & Forwarded for Forensic Examination.
              </div>
            </div>
            <div style="margin-top: 18px; margin-left: auto; width: 280px; text-align: right; line-height: 1.35;">
              <br/>
              <strong>Circle Incharge</strong><br/>
              National Cyber Crime Investigation Agency<br/>
              {$rcName}
            </div>
          </div>

          <!-- ── 2. Endorsement: DD Forensic to AD Forensic ── -->
          <div style="margin-top: 20px; border-top: 1.5px dashed #000; padding-top: 12px; font-size: 12px;">
            <table style="width: 100%; border: none; margin: 0; font-size: 12px;">
              <tr>
                <td style="border: none; padding: 0; vertical-align: top; width: 60%;">
                  <strong>To,</strong><br/>
                  <strong>Assistant Director (Forensics),</strong><br/>
                  National Cyber Crime Investigation Agency<br/>
                  {$zoneName}
                </td>
                <td style="border: none; padding: 0; vertical-align: top; text-align: right; width: 40%;">
                  <strong>Dated:</strong> ____________________
                </td>
              </tr>
            </table>
            <div style="margin: 8px 0;">
              <strong>Remarks / Order:</strong>
              <div style="margin-top: 3px; padding: 4px 8px; border-bottom: 1px dotted #555; min-height: 20px; font-style: italic;">
                Marked to AD (Forensics) / Forensic Examiner for examination and detailed forensic report.
              </div>
            </div>
            <div style="margin-top: 18px; margin-left: auto; width: 280px; text-align: right; line-height: 1.35;">
              <br/>
              <strong>Assistant Director (Forensics)</strong><br/>
              National Cyber Crime Investigation Agency<br/>
              {$zoneName}
            </div>
          </div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4 portrait; margin: 12mm 14mm; }
             body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; }
             .forensic-req-doc { width: 100%; max-width: 175mm; margin: 0 auto; font-size: 12.5px; line-height: 1.45; }
             .to-header { font-size: 13px; line-height: 1.4; margin-bottom: 12px; }
             .subject-block { font-size: 13px; font-weight: 800; text-transform: uppercase; margin-bottom: 12px; border-bottom: 1.5px solid #000; padding-bottom: 4px; }
             .salutation { font-size: 13px; margin-bottom: 8px; }
             .req-body p { margin: 6px 0; text-align: justify; }
             .sec-title { font-size: 13px; font-weight: 800; text-transform: uppercase; margin: 12px 0 6px 0; text-decoration: underline; }
             .forensic-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 12px; }
             .forensic-table th, .forensic-table td { border: 1px solid #000; padding: 5px 8px; }
             .forensic-table th { background: #f0f0f0; text-align: left; }
             .req-sign-block { margin-top: 25px; margin-left: auto; width: 280px; text-align: right; font-size: 12px; line-height: 1.35; }'
        );
    }

    /**
     * Printable Permission to Conduct a Raid in Case FIR No. matching PDF Page 4.
     */
    public function raidPermissionPrintDocument(Enquiry $enquiry, array $teamMembers = [], array $details = [], ?int $activityId = null): string
    {
        $enquiry->loadMissing(['complaint.circle', 'officer', 'accusedPersons', 'caseFile']);

        $circle     = $enquiry->complaint?->circle;
        $circleName = e($circle?->name ?? 'Lahore');
        $enquiryNo  = e($enquiry->enquiry_number ?: ($enquiry->complaint?->tracking_no ?: ('ENQ-' . $enquiry->id)));
        $firNo      = e($enquiry->caseFile?->fir_no ?: $enquiryNo);
        $kota       = e(trim((string) ($details['kota'] ?? '')) ?: 'subject cited premises');
        $against    = trim((string) ($details['against_whom'] ?? ''));
        $whenRaw    = trim((string) ($details['scheduled_at'] ?? ''));
        $whenText   = $whenRaw !== '' ? e(date('d/m/Y h:i A', strtotime($whenRaw))) : '________________';
        $subject    = e(trim((string) ($details['subject'] ?? '')) ?: ('PERMISSION TO CONDUCT A RAID IN CASE FIR NO. ' . html_entity_decode($firNo)));
        $compName   = e($enquiry->complaint?->complainant_name ?: 'the complainant');
        $offence    = e($enquiry->complaint?->offence_type ?: ($enquiry->charge_against ?: 'scam / harassment'));
        $facts      = trim((string) ($enquiry->complaint?->description ?: ''));
        $results    = $facts !== '' ? e(\Illuminate\Support\Str::limit(strip_tags($facts), 280)) : 'loss / harassment';

        // Accused List
        $accList = $enquiry->accusedPersons;
        $accRows = '';
        if ($accList && $accList->count() > 0) {
            foreach ($accList as $i => $acc) {
                $n = $i + 1;
                $addr = $acc->permanent_address ?: ($acc->postal_address ?: '—');
                $accRows .= "<div><strong>{$n}: " . e($acc->name) . ' s/o ' . e($acc->father_name ?: '—') . ' r/o ' . e($addr) . ' .</strong></div>';
            }
        } else {
            $accRows = '<div><strong>1: Name of Accused s/o r/o .</strong></div><div><strong>2: ABC s/o r/o .</strong></div>';
        }
        if ($against !== '' && (!$accList || $accList->count() === 0)) {
            $accRows = '<div><strong>1: ' . e($against) . '.</strong></div>';
        }
        $againstHtml = e($against !== '' ? $against : 'the alleged person(s)');
        $brief = trim((string) ($details['description'] ?? ''));
        $briefHtml = $brief !== '' ? nl2br(e($brief)) : "the complainant <strong>{$compName}</strong> alleged that he has been scammed / harassed by which results <strong>{$results}</strong> under <strong>{$offence}</strong>. The complainant requested to trace the culprit and take legal action against him.";

        // Raiding team
        $teamRows = '';
        if (count($teamMembers) > 0) {
            foreach ($teamMembers as $i => $tm) {
                $n = $i + 1;
                $teamRows .= "<div>{$n}. " . e($tm) . "</div>";
            }
        } else {
            $teamRows = <<<HTML
            <div>1. Sub Inspector ____________________________________</div>
            <div>2. Assistant Sub Inspector ___________________________</div>
            <div>3. Constable / Technical Officer _____________________</div>
            HTML;
        }

        $officer      = $enquiry->officer ?: request()->user();
        $officerName  = e($officer?->name ?: 'Sub-Inspector');
        $officerDesig = e($officer?->designation ?: 'Sub-Inspector');
        $officerSigHtml = $this->getOfficerSignatureHtml($officer);
        $qrHtml = $this->documentQr('raid', $activityId ?: (int) $enquiry->id, [
            'Type'       => 'Raid Permission',
            'Enquiry No' => $enquiry->enquiry_number ?: ('ENQ-' . $enquiry->id),
            'Circle'     => $circle?->name ?? '—',
            'Officer'    => $officer?->name ?: '—',
        ], 64, $enquiryNo);

        $body = <<<HTML
        <div class="raid-doc">
          <table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
            <tr>
              <td style="width:80px;border:none;vertical-align:top;">{$qrHtml}</td>
              <td style="border:none;vertical-align:top;">
          <div class="raid-to">
            To<br/>
            <strong>The Additional Director</strong><br/>
            <strong>NCCIA Cybercrime Zone</strong><br/>
            <strong>{$circleName}.</strong>
          </div>
              </td>
            </tr>
          </table>

          <div class="raid-subj">
            <strong>Subject: - {$subject}</strong>
          </div>

          <div class="raid-body">
            <p>
              Brief facts of the subject investigation are that {$briefHtml}
            </p>
            <p>
              Raid / search is to be conducted at <strong>{$kota}</strong> against <strong>{$againstHtml}</strong> on <strong>{$whenText}</strong>.
            </p>

            <div class="raid-accused" style="margin: 12px 0;">
              {$accRows}
            </div>

            <p>
              However, it is strongly believed that the sufficient evidence / digital media is present at <strong>{$kota}</strong>. It is therefore, requested that the Search Warrant of the subject cited places / locations may kindly be granted to proceed further into the matter.
            </p>

            <p>
              Therefore, it is therefore requested that following raiding team may kindly be allowed to conduct a raid as per law to stop the illegal acts of the alleged persons, please.
            </p>

            <div class="raid-team" style="margin: 14px 0 20px 10px; line-height: 1.8;">
              {$teamRows}
            </div>
          </div>

          <div class="raid-sign">
            {$officerSigHtml}
            <strong>{$officerName}</strong><br/>
            {$officerDesig}<br/>
            <strong>NCCIA/CCRC/{$circleName}</strong>
          </div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4 portrait; margin: 16mm 18mm; }
             body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; }
             .raid-doc { width: 100%; max-width: 170mm; margin: 0 auto; font-size: 13px; line-height: 1.5; }
             .raid-to { font-size: 13.5px; line-height: 1.4; margin-bottom: 16px; }
             .raid-subj { font-size: 13.5px; font-weight: 800; text-transform: uppercase; margin-bottom: 16px; border-bottom: 1.5px solid #000; padding-bottom: 4px; }
             .raid-body p { margin: 10px 0; text-align: justify; }
             .raid-sign { margin-top: 45px; margin-left: auto; width: 240px; text-align: center; font-size: 13px; line-height: 1.4; }'
        );
    }

    /**
     * Printable Search Warrant U/S 33 of PECA-2016 matching PDF Page 5.
     */
    public function searchWarrantPrintDocument(Enquiry $enquiry, array $details = [], ?int $activityId = null): string
    {
        $enquiry->loadMissing(['complaint.circle', 'officer', 'accusedPersons', 'caseFile']);

        $circle     = $enquiry->complaint?->circle;
        $circleName = e($circle?->name ?? 'LAHORE');
        $circleCode = e($circle?->code ?? 'LHR');
        $enquiryNo  = e($enquiry->enquiry_number ?: ($enquiry->complaint?->tracking_no ?: ('ENQ-' . $enquiry->id)));
        $firNo      = e($enquiry->caseFile?->fir_no ?: $enquiryNo);
        $kota       = e(trim((string) ($details['kota'] ?? '')) ?: 'mentioned locations');
        $against    = trim((string) ($details['against_whom'] ?? ''));
        $whenRaw    = trim((string) ($details['scheduled_at'] ?? ''));
        $whenText   = $whenRaw !== '' ? e(date('d/m/Y h:i A', strtotime($whenRaw))) : '';
        $subject    = e(trim((string) ($details['subject'] ?? '')) ?: 'SEARCH WARRANT U/S 33 OF PECA-2016');
        $brief      = trim((string) ($details['description'] ?? ''));

        $compName   = e($enquiry->complaint?->complainant_name ?: 'the complainant');
        $accList    = $enquiry->accusedPersons;
        $accRows    = '';
        if ($accList && $accList->count() > 0) {
            foreach ($accList as $i => $acc) {
                $n = $i + 1;
                $addr = $acc->permanent_address ?: ($acc->postal_address ?: '—');
                $accRows .= '<div><strong>' . $n . ': ACCUSED ' . e($acc->name) . ' S/O ' . e($acc->father_name ?: '—') . ' R/O ' . e($addr) . '.</strong></div>';
            }
            $firstAcc = $accList->first();
        } else {
            $accRows = '<div><strong>1: ACCUSED ANEM S/O R/O .</strong></div>';
            $firstAcc = null;
        }
        if ($against !== '' && (!$accList || $accList->count() === 0)) {
            $accRows = '<div><strong>1: ACCUSED ' . e($against) . '.</strong></div>';
        }
        $accName    = e($against !== '' ? $against : ($firstAcc?->name ?: 'Accused Person'));
        $accAddr    = e($firstAcc?->permanent_address ?: ($firstAcc?->postal_address ?: $kota));
        $allegation = e($enquiry->brief_allegation ?: ($enquiry->charge_against ?: ($enquiry->complaint?->offence_type ?: 'cybercrime offences / unauthorized access')));
        $briefHtml  = $brief !== '' ? nl2br(e($brief)) : "An enquiry no <strong>{$enquiryNo}</strong> was registered on the complaint of <strong>{$compName}</strong> that a person namely <strong>{$accName}</strong> r/o <strong>{$accAddr}</strong>, found involved in <strong>{$allegation}</strong>. The complainant requested for strict legal action against the culprits.";
        $whenLine   = $whenText !== '' ? "<p>The search is to be conducted on <strong>{$whenText}</strong> at <strong>{$kota}</strong>.</p>" : "<p>The search is to be conducted at <strong>{$kota}</strong>.</p>";
        $qrHtml = $this->documentQr('warrant', $activityId ?: (int) $enquiry->id, [
            'Type'        => 'Search Warrant',
            'Enquiry No'  => $enquiry->enquiry_number ?: ('ENQ-' . $enquiry->id),
            'FIR No'      => $enquiry->caseFile?->fir_no ?: '—',
            'Complainant' => $compName,
            'Circle'      => $circle?->name ?? '—',
        ], 64, $firNo);

        $body = <<<HTML
        <div class="warrant-doc">
          <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
            <tr>
              <td style="width:80px;border:none;vertical-align:top;">{$qrHtml}</td>
              <td style="border:none;text-align:center;vertical-align:middle;">
          <div class="court-header">
            <strong>IN THE HONORABLE COURT OF JUDICIAL MAGISTRATE,</strong><br/>
            <strong>{$circleName}.</strong>
          </div>
              </td>
            </tr>
          </table>

          <table class="court-meta">
            <tr>
              <td style="text-align: left;"><strong>P.S: Cyber Crime Circle</strong></td>
              <td style="text-align: right;"><strong>Distt: {$circleName}</strong></td>
            </tr>
          </table>

          <div class="warrant-banner">
            {$subject}
          </div>

          <div class="warrant-salutation"><strong>Respected Sir</strong></div>

          <div class="warrant-body">
            <p>
              {$briefHtml}
            </p>

            <div class="warrant-accused-box" style="margin: 14px 0;">
              {$accRows}
            </div>

            {$whenLine}

            <p>
              However, it is strongly believed that the sufficient evidence / digital media is present at <strong>{$kota}</strong> against <strong>{$accName}</strong>.
            </p>

            <p>
              It is however, requested that the Search Warrant of the above said premises may kindly be granted in order to conduct search and seize on the pretext to effect the arrest of accused persons to proceed further into the matter.
            </p>
          </div>

          <div class="warrant-sign-block">
            <strong>SI</strong><br/>
            <strong>NCCIA/CCRC/{$circleCode}</strong>
          </div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4 portrait; margin: 16mm 18mm; }
             body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; }
             .warrant-doc { width: 100%; max-width: 170mm; margin: 0 auto; font-size: 13.5px; line-height: 1.55; }
             .court-header { text-align: center; font-size: 14px; font-weight: 800; text-transform: uppercase; margin-bottom: 12px; }
             .court-meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 13px; }
             .warrant-banner { text-align: center; font-size: 14px; font-weight: 800; text-transform: uppercase; text-decoration: underline; margin-bottom: 16px; letter-spacing: 0.5px; }
             .warrant-salutation { font-size: 13.5px; margin-bottom: 10px; }
             .warrant-body p { margin: 12px 0; text-align: justify; }
             .warrant-sign-block { margin-top: 50px; margin-left: auto; width: 200px; text-align: center; font-size: 13.5px; line-height: 1.4; }'
        );
    }

    /**
     * Printable Arrest Warrant matching the Search Warrant court format.
     */
    public function arrestWarrantPrintDocument(Enquiry $enquiry, array $details = [], ?int $activityId = null): string
    {
        $enquiry->loadMissing(['complaint.circle', 'officer', 'accusedPersons', 'caseFile']);

        $circle     = $enquiry->complaint?->circle;
        $circleName = e($circle?->name ?? 'LAHORE');
        $circleCode = e($circle?->code ?? 'LHR');
        $enquiryNo  = e($enquiry->enquiry_number ?: ($enquiry->complaint?->tracking_no ?: ('ENQ-' . $enquiry->id)));
        $firNo      = e($enquiry->caseFile?->fir_no ?: $enquiryNo);
        $kota       = e(trim((string) ($details['kota'] ?? '')) ?: 'subject cited location');
        $against    = trim((string) ($details['against_whom'] ?? ''));
        $whenRaw    = trim((string) ($details['scheduled_at'] ?? ''));
        $whenText   = $whenRaw !== '' ? e(date('d/m/Y h:i A', strtotime($whenRaw))) : '';
        $subject    = e(trim((string) ($details['subject'] ?? '')) ?: 'ARREST WARRANT');
        $brief      = trim((string) ($details['description'] ?? ''));

        $compName   = e($enquiry->complaint?->complainant_name ?: 'the complainant');
        $accList    = $enquiry->accusedPersons;
        $accRows    = '';
        if ($accList && $accList->count() > 0) {
            foreach ($accList as $i => $acc) {
                $n = $i + 1;
                $addr = $acc->permanent_address ?: ($acc->postal_address ?: '—');
                $accRows .= '<div><strong>' . $n . ': ' . e($acc->name) . ' s/o ' . e($acc->father_name ?: '—') . ' r/o ' . e($addr) . '.</strong></div>';
            }
        } else {
            $accRows = $against !== ''
                ? '<div><strong>1: ' . e($against) . '.</strong></div>'
                : '<div><strong>1: Name of Accused s/o r/o .</strong></div>';
        }
        $allegation = e($enquiry->charge_against ?: ($enquiry->complaint?->offence_type ?: 'cybercrime offences / unauthorized access'));
        $againstHtml = e($against !== '' ? $against : 'the accused person(s)');
        $briefHtml = $brief !== ''
            ? nl2br(e($brief))
            : "An enquiry / FIR no <strong>{$firNo}</strong> was registered on the complaint of <strong>{$compName}</strong> that the following person(s) found involved in <strong>{$allegation}</strong>. The complainant requested for strict legal action against the culprits.";
        $whenLine = $whenText !== ''
            ? "<p>The arrest is to be effected on <strong>{$whenText}</strong> against <strong>{$againstHtml}</strong> at <strong>{$kota}</strong>.</p>"
            : "<p>The arrest is to be effected against <strong>{$againstHtml}</strong> at <strong>{$kota}</strong>.</p>";
        $qrHtml = $this->documentQr('arrest', $activityId ?: (int) $enquiry->id, [
            'Type'        => 'Arrest Warrant',
            'Enquiry No'  => $enquiry->enquiry_number ?: ('ENQ-' . $enquiry->id),
            'FIR No'      => $enquiry->caseFile?->fir_no ?: '—',
            'Complainant' => $compName,
            'Circle'      => $circle?->name ?? '—',
        ], 64, $firNo);

        $officer      = $enquiry->officer ?: request()->user();
        $officerName  = e($officer?->name ?: 'SI');
        $officerDesig = e($officer?->designation ?: 'SI');
        $officerSigHtml = $this->getOfficerSignatureHtml($officer);

        $body = <<<HTML
        <div class="warrant-doc">
          <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
            <tr>
              <td style="width:80px;border:none;vertical-align:top;">{$qrHtml}</td>
              <td style="border:none;text-align:center;vertical-align:middle;">
          <div class="court-header">
            <strong>IN THE HONORABLE COURT OF JUDICIAL MAGISTRATE,</strong><br/>
            <strong>{$circleName}.</strong>
          </div>
              </td>
            </tr>
          </table>

          <table class="court-meta">
            <tr>
              <td style="text-align: left;"><strong>P.S: Cyber Crime Circle</strong></td>
              <td style="text-align: right;"><strong>Distt: {$circleName}</strong></td>
            </tr>
          </table>

          <div class="warrant-banner">
            {$subject}
          </div>

          <div class="warrant-salutation"><strong>Respected Sir</strong></div>

          <div class="warrant-body">
            <p>
              {$briefHtml}
            </p>

            <div class="warrant-accused-box" style="margin: 14px 0;">
              {$accRows}
            </div>

            {$whenLine}

            <p>
              However, it is strongly believed that the accused person(s) are involved in the subject cited offence and are evading lawful process.
            </p>

            <p>
              It is therefore requested that the Arrest Warrant of the above said accused person(s) may kindly be granted in order to effect the arrest and to proceed further into the matter.
            </p>
          </div>

          <div class="warrant-sign-block">
            {$officerSigHtml}
            <strong>{$officerName}</strong><br/>
            {$officerDesig}<br/>
            <strong>NCCIA/CCRC/{$circleCode}</strong>
          </div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4 portrait; margin: 16mm 18mm; }
             body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; color:#000; background:#fff; }
             .warrant-doc { width: 100%; max-width: 170mm; margin: 0 auto; font-size: 13.5px; line-height: 1.55; }
             .court-header { text-align: center; font-size: 14px; font-weight: 800; text-transform: uppercase; margin-bottom: 12px; }
             .court-meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 13px; }
             .warrant-banner { text-align: center; font-size: 14px; font-weight: 800; text-transform: uppercase; text-decoration: underline; margin-bottom: 16px; letter-spacing: 0.5px; }
             .warrant-salutation { font-size: 13.5px; margin-bottom: 10px; }
             .warrant-body p { margin: 12px 0; text-align: justify; }
             .warrant-sign-block { margin-top: 50px; margin-left: auto; width: 200px; text-align: center; font-size: 13.5px; line-height: 1.4; }'
        );
    }

    public function diaryPrintDocument(Enquiry $enquiry, EnquiryActivity $activity): string
    {
        $enquiry->loadMissing('complaint.circle');
        $logo = url('images/images.jpg');
        $enquiryNo = e($enquiry->enquiry_number ?: ('#' . $enquiry->id));
        $tracking = e($enquiry->complaint?->tracking_no ?? '—');
        $circle = e($enquiry->complaint?->circle?->name ?? '—');
        $diaryNo = e($activity->diary_no ?: ('D-' . $activity->id));
        $date = $activity->activity_date ? $activity->activity_date->format('d/m/Y') : '—';
        $desc = e($activity->description ?? '');
        $issuedAt = now()->format('d/m/Y h:i A');
        $qrHtml = $this->documentQr('diary', (int) $activity->id, [
            'Type'       => 'Case Diary',
            'Diary No'   => $activity->diary_no ?: ('D-' . $activity->id),
            'Enquiry No' => $enquiry->enquiry_number ?: ('#' . $enquiry->id),
            'Complaint No' => $enquiry->complaint?->tracking_no ?? '—',
        ], 64, $diaryNo);

        $body = <<<HTML
        <div class="diary">
          <table style="width:100%;border-collapse:collapse;">
            <tr>
              <td style="width:80px;border:none;vertical-align:middle;">{$qrHtml}</td>
              <td style="border:none;text-align:center;vertical-align:middle;">
          <div class="center">
            <div class="tag">CASE DIARY</div>
          </div>
              </td>
              <td style="width:80px;border:none;"></td>
            </tr>
          </table>
          <hr/>
          <div class="mrow"><span class="k">Diary No:</span><span>{$diaryNo}</span></div>
          <div class="mrow"><span class="k">Diary Date:</span><span>{$date}</span></div>
          <div class="mrow"><span class="k">Enquiry No:</span><span>{$enquiryNo}</span></div>
          <div class="mrow"><span class="k">Complaint No:</span><span>{$tracking}</span></div>
          <div class="mrow"><span class="k">Circle:</span><span>{$circle}</span></div>
          <hr/>
          <div class="body"><strong>Description</strong><p class="desc">{$desc}</p></div>
          <div class="sign center">
            {$this->getOfficerSignatureHtml($enquiry->officer, 35)}
            <div class="sign-line"></div>
            <div class="small">Enquiry Officer</div>
          </div>
          <div class="small center">Printed: {$issuedAt}</div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4; margin: 14mm; }
             body { margin:0; font-family: Arial, Helvetica, sans-serif; color:#000; }
             .diary { max-width: 170mm; margin: 0 auto; font-size: 13px; line-height:1.5; }
             .center { text-align:center; }
             .logo { width:70px; height:70px; object-fit:contain; }
             .org { font-size:15px; font-weight:700; margin-top:4px; }
             .tag { font-size:16px; font-weight:800; letter-spacing:1px; margin-top:6px; }
             .mrow { margin:4px 0; }
             .mrow .k { display:inline-block; width:120px; font-weight:700; }
             .body { margin-top:12px; }
             .desc { white-space:pre-wrap; margin-top:6px; }
             .sign { margin:40px auto 0 auto; width:180px; }
             .sign-line { border-top:1px solid #000; margin-bottom:4px; }
             .small { font-size:11px; }
             hr { border:none; border-top:1px solid #000; margin:10px 0; }'
        );
    }

    public function accountOpeningPrintDocument(Enquiry $enquiry, array $params = []): string
    {
        try {
            $enquiry->loadMissing(['complaint.circle', 'complaint.zone', 'officer']);
        } catch (\Throwable $e) {}

        $circleName = e($enquiry->complaint?->circle?->name ?: 'Lahore');
        $enquiryNo  = e($enquiry->enquiry_number ?: ('#' . $enquiry->id));
        $officer      = $enquiry->officer ?: request()->user();
        $officerName  = e($officer?->name ?: 'Enquiry Officer');
        $officerDesig = e($officer?->designation ?: 'Sub-Inspector');
        $officerSigHtml = $this->getOfficerSignatureHtml($officer, 38);

        // Dual Logos: NCCIA (Left) and Ministry of Interior / Government of Pakistan (Right)
        $ncciaLogo    = url('images/images.jpg');
        $interiorLogo = url('images/pak-govt-logo.png');

        // System generated Date & Time
        $dateTimeStr  = now()->format('d-m-Y h:i A');

        // Dynamic EO vs IO designation & label
        $officerRole = strtolower(($officer?->designation ?? '') . ' ' . ($officer?->role ?? '') . ' ' . ($officer?->name ?? ''));
        $isIo = str_contains($officerRole, 'io') 
             || str_contains($officerRole, 'investigation') 
             || $enquiry->hasRegisteredCase()
             || !empty($enquiry->case_file_id);
        $officerTypeLabel = $isIo ? 'IO' : 'EO';

        $fraudAmount = '';
        if (!empty($params['fraud_amount'])) {
            $fraudAmount = e($params['fraud_amount']);
        } elseif (!empty($enquiry->complaint?->amount_involved)) {
            $amt = $enquiry->complaint->amount_involved;
            $fraudAmount = is_numeric($amt) ? ('PKR ' . number_format((float)$amt)) : e((string)$amt);
        }

        $fraudType = !empty($params['fraud_type'])
            ? e($params['fraud_type'])
            : e($enquiry->complaint?->offence_type ?: '');

        $accountNo       = !empty($params['account_no']) ? e($params['account_no']) : '';
        $cnic            = !empty($params['cnic']) ? e($params['cnic']) : '';
        $accountTitle    = !empty($params['account_title']) ? e($params['account_title']) : '';
        $bankName        = !empty($params['bank_name']) ? e($params['bank_name']) : '';
        $reason          = !empty($params['reason']) ? e($params['reason']) : '';
        $recoveredAmount = !empty($params['recovered_amount']) ? e($params['recovered_amount']) : '';
        $modeOfRecovery  = !empty($params['mode_of_recovery']) ? e($params['mode_of_recovery']) : '';
        $qrHtml = $this->documentQr('account', (int) $enquiry->id, [
            'Type'       => 'Account Opening Request',
            'Enquiry No' => $enquiry->enquiry_number ?: ('#' . $enquiry->id),
            'Circle'     => $enquiry->complaint?->circle?->name ?: 'Lahore',
            'Officer'    => $officer?->name ?: '—',
        ], 62, $enquiryNo);

        $body = <<<HTML
        <div class="proforma-doc">
          <table class="proforma-top-table">
            <tr>
              <td class="top-logo-left">
                {$qrHtml}
                <img src="{$ncciaLogo}" alt="NCCIA" class="proforma-logo" />
              </td>
              <td class="top-center-cell">
                <div class="agency-nccia">NCCIA</div>
                <div class="agency-title">NATIONAL CYBER CRIME INVESTIGATION AGENCY</div>
                <div class="agency-sub">MINISTRY OF INTERIOR &bull; GOVERNMENT OF PAKISTAN</div>
                <div class="center-name">Cyber Crime Reporting Center, {$circleName}</div>
              </td>
              <td class="top-logo-right">
                <img src="{$interiorLogo}" alt="Ministry of Interior" class="proforma-logo" />
              </td>
            </tr>
          </table>

          <div class="proforma-meta-row">
            <div><strong>PS:</strong> <u>NCCIA, CCRC, {$circleName}</u></div>
            <div><strong>Distt:</strong> <u>{$circleName}</u></div>
            <div><strong>Generated:</strong> <u>{$dateTimeStr}</u></div>
          </div>

          <div class="proforma-title">
            ACCOUNT OPENING REQUEST PROFORMA
          </div>

          <div class="form-body">
            <div class="field-row">
              <strong>Enquiry / Case No.:</strong> <span class="line-fill">{$enquiryNo}</span>
            </div>

            <div class="field-row">
              <strong>Total Fraud involved:</strong> <span class="line-fill">{$fraudAmount}</span>
            </div>

            <div class="field-block">
              <div><strong>Fraud Type:</strong> <span class="hint">(i.e. Online Investment, earning, crypto, impersonation etc.)</span></div>
              <div class="full-line">{$fraudType}</div>
            </div>

            <div class="field-row">
              <strong>Account No.:</strong> <span class="line-fill">{$accountNo}</span>
            </div>

            <div class="field-row">
              <strong>Or CNIC:</strong> <span class="line-fill">{$cnic}</span>
            </div>

            <div class="field-row">
              <strong>Account Title:</strong> <span class="line-fill">{$accountTitle}</span>
            </div>

            <div class="field-row">
              <strong>Bank Name:</strong> <span class="line-fill">{$bankName}</span>
            </div>

            <div class="field-block">
              <div><strong>Reason of Removal of Debit Block:</strong></div>
              <div class="full-line">{$reason}</div>
              <div class="full-line empty-line"></div>
            </div>

            <div class="field-row">
              <strong>Amount Recovered / Returned:</strong> <span class="line-fill">{$recoveredAmount}</span>
            </div>

            <div class="field-row">
              <strong>Mode of Recovery:</strong> <span class="line-fill">{$modeOfRecovery}</span>
            </div>

            <div class="closing-text">
              It is requested to kindly accord permission to unblock / unfreeze the above-mentioned account or CNIC, please.
            </div>
          </div>

          <div class="sign-section">
            <div><strong>Signature with date:</strong> {$officerSigHtml}</div>
            <div style="margin-top: 6px;"><strong>Name of {$officerTypeLabel}:</strong> {$officerName}</div>
            <div><strong>Designation:</strong> {$officerDesig}</div>
            <div style="font-size: 11px; color: #475569; margin-top: 2px;">Date: {$dateTimeStr}</div>
          </div>

          <div class="authorities-section">
            <div><strong>1. DD/InCharge/CCRC/ICT:</strong></div>
            <div class="auth-line"></div>

            <div style="margin-top: 36px;"><strong>2. Addl/Director (Zone):</strong></div>
            <div class="auth-line"></div>
          </div>
        </div>
        HTML;

        return $this->document(
            $body,
            '@page { size: A4 portrait; margin: 16mm 20mm; }
             body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; line-height: 1.5; }
             .proforma-doc { width: 100%; max-width: 172mm; margin: 0 auto; font-size: 13px; }
             .proforma-top-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
             .proforma-top-table td { vertical-align: middle; padding: 0; }
             .top-logo-left { width: 65px; text-align: left; }
             .top-logo-right { width: 65px; text-align: right; }
             .proforma-logo { width: 60px; height: 60px; object-fit: contain; }
             .top-center-cell { text-align: center; padding: 0 10px; }
             .agency-nccia { font-size: 22px; font-weight: 800; letter-spacing: 4px; line-height: 1.1; }
             .agency-title { font-size: 13px; font-weight: 800; text-transform: uppercase; color: #000; letter-spacing: 0.5px; }
             .agency-sub { font-size: 10.5px; font-weight: 700; color: #333; margin-top: 2px; letter-spacing: 0.5px; }
             .center-name { font-size: 12px; font-weight: 700; color: #111; margin-top: 2px; }
             .proforma-meta-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 12px; margin: 8px 0 16px 0; border-top: 1.5px solid #000; border-bottom: 1.5px solid #000; padding: 4px 0; }
             .proforma-title { text-align: center; font-size: 14.5px; font-weight: 800; text-decoration: underline; margin-bottom: 20px; letter-spacing: 0.5px; text-transform: uppercase; }
             .form-body { line-height: 2.1; }
             .field-row { margin-bottom: 5px; }
             .field-block { margin-bottom: 6px; }
             .hint { font-size: 11.5px; color: #333; font-weight: normal; }
             .line-fill { display: inline-block; border-bottom: 1px solid #000; min-width: 300px; padding-left: 8px; font-weight: 600; }
             .full-line { border-bottom: 1px solid #000; min-height: 22px; padding-left: 8px; }
             .empty-line { margin-top: 5px; }
             .closing-text { margin-top: 14px; margin-bottom: 16px; text-align: justify; line-height: 1.45; font-size: 13px; }
             .sign-section { margin-top: 28px; margin-left: auto; width: 250px; font-size: 12.5px; line-height: 1.5; }
             .authorities-section { margin-top: 36px; font-size: 13px; line-height: 1.7; }
             .auth-line { border-bottom: 1px solid #000; width: 360px; min-height: 22px; margin-top: 5px; }',
            false
        );
    }

    private function letterheadHtml(): string
    {
        $nccia = e(url('images/images.jpg'));
        $interior = e(url('images/pak-govt-logo.png'));

        return <<<HTML
<table class="nccia-letterhead">
  <tr>
    <td class="lh-left"><img src="{$nccia}" alt="NCCIA" class="lh-logo" /></td>
    <td class="lh-center">
      <div class="lh-nccia">NCCIA</div>
      <div class="lh-full">National Cyber Crime Investigation Agency</div>
      <div class="lh-sub">Ministry of Interior &bull; Government of Pakistan</div>
    </td>
    <td class="lh-right"><img src="{$interior}" alt="Ministry of Interior" class="lh-logo" /></td>
  </tr>
</table>
HTML;
    }

    private function letterheadCss(): string
    {
        return '
.nccia-letterhead { width:100%; max-width:175mm; margin:0 auto 12px auto; border-collapse:collapse; border-bottom:2px solid #000; }
.nccia-letterhead td { border:none; vertical-align:middle; padding:0 0 8px 0; }
.lh-left { width:72px; text-align:left; }
.lh-right { width:72px; text-align:right; }
.lh-center { text-align:center; padding:0 8px; }
.lh-logo { width:62px; height:62px; object-fit:contain; }
.lh-nccia { font-size:22px; font-weight:800; letter-spacing:4px; line-height:1.05; }
.lh-full { font-size:11px; font-weight:700; text-transform:uppercase; margin-top:2px; }
.lh-sub { font-size:10px; font-weight:600; margin-top:1px; }';
    }

    private function document(string $body, string $css, bool $letterhead = true): string
    {
        $head = $letterhead ? $this->letterheadHtml() : '';
        $lhCss = $letterhead ? $this->letterheadCss() : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $lhCss . $css . '</style></head><body>' . $head . $body . '</body></html>';
    }
}



