@php
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-m-Y') : '__________';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Proclamation u/s 87 CrPC — {{ $p->fir_no ?: ('Case #'.$p->case_file_id) }}</title>
<style>
    @page { size: A4; margin: 22mm 20mm; }
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Georgia, serif; color: #111; line-height: 1.7; font-size: 15px; margin: 0; }
    .sheet { max-width: 800px; margin: 0 auto; padding: 24px; }
    .head { text-align: center; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 18px; }
    .agency { font-size: 22px; font-weight: 700; letter-spacing: .04em; }
    .agency-full { font-size: 14px; }
    .gov { font-size: 12px; color: #444; }
    .title { text-align: center; font-weight: 700; text-transform: uppercase; margin: 18px 0 4px; font-size: 17px; letter-spacing: .02em; }
    .section-ref { text-align: center; font-style: italic; color: #333; margin-bottom: 20px; }
    .meta { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 18px; }
    .body p { margin: 0 0 14px; text-align: justify; }
    .u { border-bottom: 1px solid #111; padding: 0 6px; font-weight: 600; }
    .sign { margin-top: 56px; display: flex; justify-content: space-between; }
    .sign div { text-align: center; width: 45%; }
    .sign .line { border-top: 1px solid #111; padding-top: 6px; font-size: 13px; }
    .foot { margin-top: 30px; font-size: 12px; color: #555; border-top: 1px dashed #999; padding-top: 8px; }
    @media print { .sheet { padding: 0; } }
</style>
</head>
<body>
<div class="sheet">
    <div class="head">
        <div class="agency">NCCIA</div>
        <div class="agency-full">National Cyber Crime Investigation Agency</div>
        <div class="gov">Ministry of Interior &bull; Government of Pakistan</div>
    </div>

    <div class="title">Proclamation Requiring the Appearance of an Accused Person</div>
    <div class="section-ref">(Under Section 87 of the Code of Criminal Procedure, 1898)</div>

    <div class="meta">
        <span><strong>FIR No:</strong> {{ $p->fir_no ?: '—' }}</span>
        <span><strong>Court:</strong> {{ $p->court_name ?: '—' }}</span>
        <span><strong>Dated:</strong> {{ $fmt($p->proclaimed_on) }}</span>
    </div>

    <div class="body">
        <p>Whereas a warrant has been issued in the above-noted case against
            <span class="u">{{ $p->accused_name }}</span>
            @if($p->accused_father_name) son/daughter of <span class="u">{{ $p->accused_father_name }}</span>@endif,
            resident of <span class="u">{{ $p->accused_address ?: '____________________' }}</span>;</p>

        <p>And whereas this authority has reason to believe that the said person has absconded or is
            concealing himself/herself so that the said warrant cannot be executed;</p>

        <p>Now, therefore, proclamation is hereby made under Section 87 of the Code of Criminal Procedure, 1898,
            requiring the said <span class="u">{{ $p->accused_name }}</span> to appear before
            <span class="u">{{ $p->court_name ?: 'the Court' }}</span>
            at <span class="u">{{ $p->publication_place ?: '____________________' }}</span>
            on or before <span class="u">{{ $fmt($p->appear_by_date) }}</span>
            (being a date not less than thirty (30) days from the date of this proclamation) to answer the
            charge in respect of the offence
            @if($p->offence) of <span class="u">{{ $p->offence }}</span>@endif.</p>

        <p>Failing such appearance within the time specified, further proceedings, including attachment of
            property under Section 88 of the Code of Criminal Procedure, 1898, shall follow in accordance with law.</p>
    </div>

    <div class="sign">
        <div><div class="line">Investigation Officer</div></div>
        <div><div class="line">Circle Incharge (Approving Authority)</div></div>
    </div>

    <div class="foot">
        This proclamation is issued under Section 87 CrPC. It must be (a) publicly read in a conspicuous place of the
        locality where the accused ordinarily resides, (b) affixed to a conspicuous part of the accused's residence,
        and (c) a copy affixed to the Court-house.
    </div>
</div>
</body>
</html>
