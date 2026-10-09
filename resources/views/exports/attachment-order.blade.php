@php
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d-m-Y') : '__________';
    $p = $a->proclamation;
    $typeLabel = ['movable' => 'Movable', 'immovable' => 'Immovable', 'both' => 'Movable and Immovable'][$a->property_type] ?? ucfirst($a->property_type);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Attachment Order u/s 88 CrPC — {{ $a->order_no ?: ('#'.$a->id) }}</title>
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
    .prop { width: 100%; border-collapse: collapse; margin: 6px 0 16px; font-size: 14px; }
    .prop th, .prop td { border: 1px solid #111; padding: 7px 9px; text-align: left; vertical-align: top; }
    .prop th { width: 32%; background: #f2f2f2; }
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

    <div class="title">Order for Attachment of Property of a Proclaimed Person</div>
    <div class="section-ref">(Under Section 88 of the Code of Criminal Procedure, 1898)</div>

    <div class="meta">
        <span><strong>Order No:</strong> {{ $a->order_no ?: '—' }}</span>
        <span><strong>FIR No:</strong> {{ $p->fir_no ?? '—' }}</span>
        <span><strong>Dated:</strong> {{ $fmt($a->attachment_date) }}</span>
    </div>

    <div class="body">
        <p>Whereas a proclamation under Section 87 of the Code of Criminal Procedure, 1898 was issued against
            <span class="u">{{ $p->accused_name ?? '____________' }}</span>
            @if($p && $p->accused_father_name) son/daughter of <span class="u">{{ $p->accused_father_name }}</span>@endif
            on <span class="u">{{ $fmt($p->proclaimed_on ?? null) }}</span>,
            requiring appearance on or before <span class="u">{{ $fmt($p->appear_by_date ?? null) }}</span>;</p>

        <p>And whereas this authority is satisfied that the said proclaimed person is absconding or concealing
            himself/herself to avoid execution of process;</p>

        <p>Now, therefore, under Section 88 of the Code of Criminal Procedure, 1898, it is ordered that the
            following <span class="u">{{ $typeLabel }}</span> property belonging to the said person be attached:</p>

        <table class="prop">
            <tr><th>Type of property</th><td>{{ $typeLabel }}</td></tr>
            <tr><th>Description</th><td>{{ $a->property_description }}</td></tr>
            <tr><th>Location</th><td>{{ $a->location ?: '—' }}</td></tr>
            <tr><th>Estimated value</th><td>{{ $a->estimated_value !== null ? 'Rs. '.number_format((float) $a->estimated_value, 2) : '—' }}</td></tr>
        </table>

        <p>The attachment shall be carried out in the manner provided by law. If the proclaimed person does not
            appear within the time specified in the proclamation, the property shall be at the disposal of the
            Government, but shall not be sold until the expiry of six months from the date of attachment and until
            any claim or objection has been disposed of under the law.</p>
    </div>

    <div class="sign">
        <div><div class="line">Investigation Officer</div></div>
        <div><div class="line">Circle Incharge (Approving Authority)</div></div>
    </div>

    <div class="foot">
        Issued under Section 88 CrPC, pursuant to the proclamation under Section 87 CrPC. Subject to restoration
        under the law if the person appears and satisfies the Court that he/she did not abscond or conceal to avoid
        execution of the warrant.
    </div>
</div>
</body>
</html>
