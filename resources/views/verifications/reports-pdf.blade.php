<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Verification Report - {{ $report->tracking_no }}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 8mm 10mm;
    }
    body {
      font-family: 'DejaVu Sans', sans-serif;
      font-size: 8pt;
      color: #111;
      line-height: 1.3;
      margin: 0;
      padding: 0;
    }
    .watermark {
      position: fixed;
      top: 30%;
      left: 5%;
      right: 5%;
      text-align: center;
      font-size: 44pt;
      font-weight: bold;
      color: rgba(0, 40, 80, 0.04);
      text-transform: uppercase;
      letter-spacing: 10px;
      z-index: -1;
      line-height: 1.5;
    }
    .page-container {
      width: 100%;
      height: 100%;
      box-sizing: border-box;
    }
    .top-half {
      border: 1.5px solid #1a3d6b;
      padding: 6px 8px;
      margin-bottom: 6px;
      background: #fff;
    }
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 4px;
    }
    .header-table td {
      vertical-align: middle;
      padding: 0;
    }
    .top-logo {
      width: 50px;
      text-align: left;
    }
    .top-logo img {
      width: 46px;
      height: 46px;
      object-fit: contain;
    }
    .top-right-logo {
      width: 50px;
      text-align: right;
    }
    .top-right-logo img {
      width: 44px;
      height: 44px;
      object-fit: contain;
    }
    .header-center {
      text-align: center;
    }
    .header-center .agency-nccia {
      font-size: 16pt;
      font-weight: 800;
      letter-spacing: 3px;
      line-height: 1.05;
      color: #1a3d6b;
      margin: 0;
    }
    .header-center .agency-title {
      font-size: 10.5pt;
      font-weight: bold;
      color: #1a3d6b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 0;
    }
    .header-center .govt-sub {
      font-size: 7pt;
      color: #444;
      font-weight: 600;
      margin: 1px 0;
      text-transform: uppercase;
    }
    .header-center .circle-title {
      font-size: 8pt;
      font-weight: bold;
      color: #222;
      margin: 0;
    }
    .report-banner {
      background: #1a3d6b;
      color: #fff;
      text-align: center;
      font-size: 8.5pt;
      font-weight: bold;
      letter-spacing: 1px;
      padding: 3px 0;
      margin: 4px 0 6px 0;
      text-transform: uppercase;
    }
    .meta-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 4px;
      font-size: 7.5pt;
    }
    .meta-table td {
      padding: 2px 4px;
      border: 1px solid #ccc;
    }
    .meta-table .lbl {
      font-weight: bold;
      background: #f1f5f9;
      color: #1a3d6b;
      width: 15%;
    }
    .meta-table .val {
      width: 35%;
    }
    .section-head {
      font-size: 7.5pt;
      font-weight: bold;
      color: #1a3d6b;
      background: #e2e8f0;
      padding: 2px 5px;
      margin: 4px 0 2px 0;
      border-left: 3px solid #1a3d6b;
      text-transform: uppercase;
    }
    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 3px;
      font-size: 7.5pt;
    }
    .info-table td {
      padding: 2px 4px;
      border: 1px solid #dcdcdc;
      vertical-align: top;
    }
    .info-table .lbl {
      font-weight: bold;
      background: #f8fafc;
      color: #333;
      width: 18%;
    }
    .info-table .val {
      width: 32%;
    }
    .accused-box {
      font-size: 7pt;
      border: 1px solid #ddd;
      padding: 2px 4px;
      background: #fafafa;
      margin-bottom: 3px;
    }
    .vo-sig-row {
      width: 100%;
      border-collapse: collapse;
      margin-top: 4px;
    }
    .vo-sig-row td {
      padding: 0;
      vertical-align: bottom;
    }
    .vo-sig-box {
      text-align: right;
      font-size: 7.5pt;
    }
    .vo-sig-line {
      border-top: 1px solid #1a3d6b;
      width: 180px;
      margin-left: auto;
      margin-top: 16px;
      margin-bottom: 2px;
    }

    /* BOTTOM HALF - CIRCLE INCHARGE SECTION */
    .bottom-half {
      border: 2px solid #0f172a;
      padding: 8px 10px;
      background: #fcfdfe;
      page-break-inside: avoid;
    }
    .ci-banner {
      background: #0f172a;
      color: #fff;
      text-align: center;
      font-size: 9pt;
      font-weight: bold;
      letter-spacing: 1.2px;
      padding: 4px 0;
      margin: 0 0 6px 0;
      text-transform: uppercase;
    }
    .ci-sub {
      font-size: 7.5pt;
      font-weight: bold;
      color: #0f172a;
      margin: 4px 0 2px 0;
      text-transform: uppercase;
    }
    .notes-box {
      border: 1.5px dashed #475569;
      background: #fff;
      padding: 6px 8px;
      margin-bottom: 8px;
      min-height: 120px;
    }
    .ruled-line {
      border-bottom: 1px dotted #94a3b8;
      height: 18px;
      margin: 0;
    }
    .marking-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 8px;
      font-size: 8pt;
    }
    .marking-table td {
      padding: 4px 6px;
      border: 1px solid #cbd5e1;
      vertical-align: middle;
    }
    .marking-table .m-lbl {
      font-weight: bold;
      background: #f1f5f9;
      color: #0f172a;
      width: 30%;
    }
    .marking-table .m-val {
      width: 70%;
    }
    .ci-sig-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 12px;
    }
    .ci-sig-table td {
      vertical-align: bottom;
      padding: 0;
    }
    .ci-sig-box {
      text-align: center;
      width: 220px;
      margin-left: auto;
      font-size: 7.5pt;
    }
    .ci-sig-line {
      border-top: 1.5px solid #0f172a;
      margin: 28px auto 3px auto;
      width: 180px;
    }
    .divider-tag {
      text-align: center;
      font-size: 6.5pt;
      color: #64748b;
      margin: 2px 0;
      letter-spacing: 2px;
      text-transform: uppercase;
    }
    .footer {
      text-align: center;
      font-size: 6.5pt;
      color: #64748b;
      margin-top: 4px;
      border-top: 1px solid #cbd5e1;
      padding-top: 2px;
    }
  </style>
</head>
<body>

<div class="watermark">
  NCCIA<br>GOVERNMENT OF PAKISTAN
</div>

<div class="page-container">

  <!-- TOP HALF: VERIFICATION DETAILS -->
  <div class="top-half">
    <table class="header-table">
      <tr>
        <td class="top-logo">
          @if(file_exists(public_path('images/images.jpg')))
          <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents(public_path('images/images.jpg'))) }}" alt="NCCIA">
          @endif
        </td>
        <td class="header-center">
          <div class="agency-nccia">NCCIA</div>
          <div class="agency-title">National Cyber Crime Investigation Agency</div>
          <div class="govt-sub">Ministry of Interior &bull; Government of Pakistan</div>
          <div class="circle-title">Reporting Center {{ $report->creator?->circle?->city ?? 'Lahore' }}</div>
        </td>
        <td class="top-right-logo">
          @if(file_exists(public_path('images/pak-govt-logo.png')))
          <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/pak-govt-logo.png'))) }}" alt="Govt Logo">
          @endif
        </td>
      </tr>
    </table>

    <div class="report-banner">
      VICTIM VERIFICATION REPORT &mdash; {{ $report->tracking_no }}
    </div>

    <table class="meta-table">
      <tr>
        <td class="lbl">Tracking No:</td>
        <td class="val"><strong>{{ $report->tracking_no }}</strong></td>
        <td class="lbl">Verif. Date:</td>
        <td class="val">{{ $report->verification_date?->format('d-m-Y h:i A') ?? now()->format('d-m-Y') }}</td>
      </tr>
      <tr>
        <td class="lbl">Registration:</td>
        <td class="val">{{ $report->registration_at?->format('d-m-Y') ?? '—' }}</td>
        <td class="lbl">Assigned Date:</td>
        <td class="val">{{ $report->assignment_date?->format('d-m-Y') ?? '—' }}</td>
      </tr>
    </table>

    <div class="section-head">1. Complainant / Victim &amp; Offence Information</div>
    <table class="info-table">
      <tr>
        <td class="lbl">Victim Name:</td>
        <td class="val"><strong>{{ $report->victim_name }}</strong></td>
        <td class="lbl">S/o, D/o, W/o:</td>
        <td class="val">{{ $report->victim_father_name ?? '—' }}</td>
      </tr>
      <tr>
        <td class="lbl">CNIC No:</td>
        <td class="val">{{ $report->victim_cnic ?: '—' }}</td>
        <td class="lbl">Contact No:</td>
        <td class="val">{{ $report->victim_country_code ?? '+92' }} {{ $report->victim_phone }}</td>
      </tr>
      <tr>
        <td class="lbl">City / District:</td>
        <td class="val">{{ $report->city ?? '—' }}</td>
        <td class="lbl">Crime Category:</td>
        <td class="val"><strong>{{ $report->crime_category ?? '—' }}</strong></td>
      </tr>
      @if($report->crime_description)
      <tr>
        <td class="lbl">Crime Gist:</td>
        <td class="val" colspan="3">{{ Str::limit($report->crime_description, 220) }}</td>
      </tr>
      @endif
    </table>

    <div class="section-head">2. Accused Details &amp; Verification Findings</div>
    <table class="info-table">
      <tr>
        <td class="lbl">Accused Details:</td>
        <td class="val" colspan="3">
          @if($report->accused_known && is_array($report->accused) && count($report->accused) > 0)
            @foreach($report->accused as $idx => $acc)
              <strong>#{{ $idx + 1 }}: {{ $acc['name'] ?? 'Unknown' }}</strong> 
              @if(!empty($acc['father_name'])) S/O {{ $acc['father_name'] }} @endif
              @if(!empty($acc['cnic'])) &bull; CNIC: {{ $acc['cnic'] }} @endif
              @if(!empty($acc['phone'])) &bull; Ph: {{ $acc['phone'] }} @endif
              @if(!empty($acc['address'])) &bull; Addr: {{ $acc['address'] }} @endif
              <br>
            @endforeach
          @else
            <em>No accused identified / recorded at verification stage.</em>
          @endif
        </td>
      </tr>
      <tr>
        <td class="lbl">VO Recommendation:</td>
        <td class="val" colspan="3">
          <strong>{{ $report->recommendation_short ?: 'Regular Enquiry Recommended' }}</strong>
          @if($report->recommendation_full)
            &mdash; {{ Str::limit($report->recommendation_full, 220) }}
          @endif
        </td>
      </tr>
    </table>

    {{-- Financial / Transaction Records --}}
    @php $txns = $report->transactions ?? []; @endphp
    @if(is_array($txns) && count($txns) > 0)
    <div class="section-head">3. Financial / Transaction Records</div>
    <table class="info-table" style="font-size:7pt;">
      <thead>
        <tr>
          <td class="lbl" style="text-align:center; width:5%;">#</td>
          <td class="lbl" style="width:28%;">Bank / Method</td>
          <td class="lbl" style="width:28%;">Account / IBAN</td>
          <td class="lbl" style="width:20%;">Amount (PKR)</td>
          <td class="lbl" style="width:19%;">Date</td>
        </tr>
      </thead>
      <tbody>
        @foreach($txns as $ti => $t)
        <tr>
          <td class="val" style="text-align:center;">{{ $ti + 1 }}</td>
          <td class="val">{{ $t['bank'] ?? '—' }}</td>
          <td class="val">{{ $t['account'] ?? '—' }}</td>
          <td class="val">{{ $t['amount'] ? number_format($t['amount']) : '—' }}</td>
          <td class="val">{{ $t['date'] ? \Carbon\Carbon::parse($t['date'])->format('d-m-Y') : '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif

    <table class="vo-sig-row">
      <tr>
        <td style="width: 50%; font-size: 7pt; color: #555;">
          @if($report->inquiry_no) Inquiry Ref: <strong>{{ $report->inquiry_no }}</strong> &nbsp;|&nbsp; @endif
          @if($report->case_no) FIR Ref: <strong>{{ $report->case_no }}</strong> @endif
        </td>
        <td style="width: 50%;">
          <div class="vo-sig-box">
            @php
              $voSigPath = $report->signature ?? $report->creator?->signature;
            @endphp
            @if($voSigPath && file_exists(public_path('storage/' . ltrim($voSigPath, '/'))))
              <div style="margin-bottom: 2px;"><img src="{{ public_path('storage/' . ltrim($voSigPath, '/')) }}" alt="Signature" style="max-height: 38px; max-width: 120px; object-fit: contain;"></div>
            @elseif($voSigPath)
              <div style="margin-bottom: 2px;"><img src="{{ url('storage/' . ltrim($voSigPath, '/')) }}" alt="Signature" style="max-height: 38px; max-width: 120px; object-fit: contain;"></div>
            @else
              <div class="vo-sig-line"></div>
            @endif
            <strong>{{ $report->creator?->name ?? 'Verification Officer' }}</strong><br>
            {{ $report->creator?->designation ?? 'Investigation / Verification Officer' }}<br>
            NCCIA {{ $report->creator?->circle?->name ?? 'Circle' }}
          </div>
        </td>
      </tr>
    </table>
  </div>

  <div class="divider-tag">&bull; &bull; &bull; CIRCLE INCHARGE REVIEW &amp; MARKING SECTION (BOTTOM HALF) &bull; &bull; &bull;</div>

  <!-- BOTTOM HALF: CIRCLE INCHARGE NOTES & MARKING TO ENQUIRY OFFICER -->
  <div class="bottom-half">
    <div class="ci-banner">
      FOR USE OF CIRCLE INCHARGE &mdash; ORDER / MARKING TO ENQUIRY OFFICER
    </div>

    {{-- Enquiry Number and Dated in separate boxes --}}
    <table style="width: 60%; margin: 0 auto 8px auto; border-collapse: collapse; font-size: 9pt; font-weight: bold;">
      <tr>
        <td style="border: 1.5px solid #1a3d6b; padding: 4px 10px; text-align: center; background: #f1f5f9; color: #1a3d6b; width: 50%;">
          Enquiry No.<br>
          <span style="font-size: 9.5pt; color: #111;">
            @if($report->complaint?->enquiry?->enquiry_number)
              {{ $report->complaint->enquiry->enquiry_number }}
            @elseif($report->inquiry_no)
              {{ $report->inquiry_no }}
            @else
              _______________
            @endif
          </span>
        </td>
        <td style="border: 1.5px solid #1a3d6b; padding: 4px 10px; text-align: center; background: #f1f5f9; color: #1a3d6b; width: 50%;">
          Dated<br>
          <span style="font-size: 9.5pt; color: #111;">
            @if($report->complaint?->enquiry?->reg_date)
              {{ $report->complaint->enquiry->reg_date->format('d-m-Y') }}
            @else
              _______________
            @endif
          </span>
        </td>
      </tr>
    </table>

    <div class="ci-sub">1. Enquiry Officer Assignment Order:</div>
    <table class="marking-table">
      <tr>
        <td class="m-lbl">Marked / Assigned to Enquiry Officer:</td>
        <td class="m-val">
          @if($report->complaint?->enquiry?->enquiryOfficer)
            <strong>{{ $report->complaint->enquiry->enquiryOfficer->name }}</strong> ({{ $report->complaint->enquiry->enquiryOfficer->designation ?? 'Enquiry Officer' }})
          @else
            ____________________________________________________________________
          @endif
        </td>
      </tr>
      <tr>
        <td class="m-lbl">obtain Legal Opinion from AD / DD Legal,</td>
        <td class="m-val">&nbsp;</td>
      </tr>
    </table>

    <div class="ci-sub" style="margin-top: 8px;">2. Directions &amp; Notes of Circle Incharge:</div>
    <div class="notes-box">
      <div class="ruled-line"></div>
      <div class="ruled-line"></div>
      <div class="ruled-line"></div>
      <div class="ruled-line"></div>
      <div class="ruled-line"></div>
      <div class="ruled-line"></div>
    </div>

    <table class="ci-sig-table">
      <tr>
        <td style="width: 100%; text-align: right;">
          <div class="ci-sig-box">
            <div class="ci-sig-line"></div>
            <strong style="font-size: 8.5pt; color: #0f172a;">Circle Incharge</strong><br>
            National Cyber Crime Investigation Agency<br>
            NCCIA-RC {{ $report->creator?->circle?->city ?? 'Lahore' }}<br>
            Date: ____________________
          </div>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    National Cyber Crime Investigation Agency &bull; Verification Document &bull; Generated: {{ now()->format('d/m/Y h:i A') }}
  </div>

</div>

</body>
</html>
