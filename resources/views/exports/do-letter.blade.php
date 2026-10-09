<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>D.O. Letter — {{ $letter->month_label }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .no-print { text-align: right; margin-bottom: 12px; }
        .no-print button {
            background: #1a3d6b;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            font-size: 12px;
        }
        @media print {
            .no-print { display: none !important; }
        }
        .nccia-letterhead { border: 1.5px solid #1a3d6b; margin-bottom: 14px; }
        .letterhead-table { width: 100%; border-collapse: collapse; }
        .letterhead-table td { border: none; vertical-align: middle; padding: 8px 10px 4px; }
        .letterhead-side { width: 72px; }
        .letterhead-left { text-align: left; }
        .letterhead-right { text-align: right; }
        .letterhead-logo-img { width: 56px; height: 56px; object-fit: contain; display: inline-block; }
        .letterhead-center { text-align: center; }
        .letterhead-agency { font-size: 24px; font-weight: 800; letter-spacing: 5px; color: #1a3d6b; line-height: 1; }
        .letterhead-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #1a3d6b; margin-top: 3px; }
        .letterhead-sub { font-size: 9.5px; font-weight: 600; color: #475569; margin-top: 2px; }
        .letterhead-banner { background: #1a3d6b; color: #fff; text-align: center; font-size: 11px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; padding: 5px 8px; }
        .letterhead-meta { text-align: center; font-size: 10px; font-weight: 600; color: #334155; padding: 4px 8px 2px; border-bottom: 1px solid #cbd5e1; }
        .letterhead-qr { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 6px 8px 8px; border-top: 1px dashed #cbd5e1; }
        .letterhead-qr-box svg { width: 52px; height: 52px; display: block; }
        .letterhead-qr-caption { font-family: Consolas, monospace; font-size: 9px; font-weight: 700; color: #1a3d6b; }
        .report-section { margin-bottom: 12px; page-break-inside: avoid; }
        .section-head { background: #e2e8f0; border: 1px solid #94a3b8; border-bottom: none; padding: 5px 8px; }
        .section-head h3 { margin: 0; font-size: 11px; font-weight: 800; text-transform: uppercase; color: #0f172a; }
        table.data-table { width: 100%; border-collapse: collapse; margin: 0; }
        .data-table thead { display: table-header-group; }
        .data-table th, .data-table td { border: 1px solid #64748b; padding: 5px 6px; overflow-wrap: break-word; }
        .data-table th { background: #f1f5f9; font-size: 9px; font-weight: 700; text-align: center; }
        .data-table td { font-size: 10px; text-align: center; }
        .data-table tbody tr:nth-child(even) td { background: #fafafa; }
        .nil-row td { text-align: center; font-weight: 700; padding: 10px; }
        .status-pill { display: inline-block; margin: 0 auto 12px; padding: 4px 10px; border: 1px solid #94a3b8; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">🖨️ Print D.O. Letter</button>
    </div>
    @include('exports.partials.nccia-official-header', [
        'qrType' => 'do_letter',
        'qrId' => $letter->id,
        'qrCaption' => 'DO-' . $letter->id,
        'documentSubtitle' => 'D.O. Letter — ' . strtoupper($letter->month_label),
        'documentMeta' => ($letter->circle?->name ?? 'Circle') . ' · Status: ' . str_replace('_', ' ', $letter->status),
    ])

    @php $p = $letter->payload ?? []; @endphp

    <div class="report-section">
        <div class="section-head"><h3>1. Cases</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>At Beginning</th><th>Added</th><th>Total</th><th>Interim Challan</th><th>Final Challan</th><th>Discharge</th><th>Transferred</th><th>Pending</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $p['cases']['at_beginning'] ?? 0 }}</td>
                    <td>{{ $p['cases']['added'] ?? 0 }}</td>
                    <td>{{ $p['cases']['total'] ?? 0 }}</td>
                    <td>{{ $p['cases']['interim_challan'] ?? 0 }}</td>
                    <td>{{ $p['cases']['final_challan'] ?? 0 }}</td>
                    <td>{{ $p['cases']['discharge'] ?? 0 }}</td>
                    <td>{{ $p['cases']['transferred'] ?? 0 }}</td>
                    <td>{{ $p['cases']['pending'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <div class="section-head"><h3>2. CFR Pendency</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>Total CFRs</th><th>Pending Legal</th><th>Pending Forensic</th><th>Under Investigation</th><th>Total Pending</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $p['cfr_pendency']['total_cfrs'] ?? 0 }}</td>
                    <td>{{ $p['cfr_pendency']['pending_legal'] ?? 0 }}</td>
                    <td>{{ $p['cfr_pendency']['pending_forensic'] ?? 0 }}</td>
                    <td>{{ $p['cfr_pendency']['pending_investigation'] ?? 0 }}</td>
                    <td>{{ $p['cfr_pendency']['total_pending'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <div class="section-head"><h3>3. Enquiries</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>At Beginning</th><th>Added</th><th>Closed</th><th>Pending</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $p['enquiries']['at_beginning'] ?? 0 }}</td>
                    <td>{{ $p['enquiries']['added'] ?? 0 }}</td>
                    <td>{{ $p['enquiries']['closed'] ?? 0 }}</td>
                    <td>{{ $p['enquiries']['pending'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <div class="section-head"><h3>4. Raids</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>Total Raids</th><th>Cases After Raid</th><th>Enquiries After Raid</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $p['raid']['total_raid'] ?? 0 }}</td>
                    <td>{{ $p['raid']['cases_after_raid'] ?? 0 }}</td>
                    <td>{{ $p['raid']['enquiries_after_raid'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <div class="section-head"><h3>5. Proclaimed Offenders (PO)</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>Previous</th><th>Added</th><th>Arrested</th><th>Pending</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $p['pos']['previous'] ?? 0 }}</td>
                    <td>{{ $p['pos']['added'] ?? 0 }}</td>
                    <td>{{ $p['pos']['arrested'] ?? 0 }}</td>
                    <td>{{ $p['pos']['pending'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <div class="section-head"><h3>6. Court Absconders (CA)</h3></div>
        <table class="data-table">
            <thead>
                <tr><th>Previous</th><th>Added</th><th>Arrested</th><th>Pending</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $p['cas']['previous'] ?? 0 }}</td>
                    <td>{{ $p['cas']['added'] ?? 0 }}</td>
                    <td>{{ $p['cas']['arrested'] ?? 0 }}</td>
                    <td>{{ $p['cas']['pending'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-section">
        <div class="section-head"><h3>Accused Arrested in {{ $letter->month_label }}</h3></div>
        @include('exports.partials.dsr-table', ['rows' => $p['accused_arrested'] ?? [], 'columns' => ['fir_no','accused_name','arrest_date','address']])
    </div>

    <div class="report-section">
        <div class="section-head"><h3>Complaints Registered in {{ $letter->month_label }}</h3></div>
        @include('exports.partials.dsr-table', ['rows' => $p['complaints_registered'] ?? [], 'columns' => ['tracking_no','diary_no','dated','complainant','cnic','contact_no','offence_type','laws','amount_involved','received_via','status']])
    </div>

    @if($letter->notes)
        <div class="report-section">
            <div class="section-head"><h3>Notes</h3></div>
            <p style="margin:8px 0; padding:0 4px;">{{ $letter->notes }}</p>
        </div>
    @endif

    <div class="report-section" style="margin-top: 36px; page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: collapse; border: none;">
            <tr>
                <td style="width: 48%; border: none; vertical-align: top; text-align: left; padding: 0;">
                    <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Submitted By:</div>
                    <div style="margin-top: 45px; border-top: 1.5px dashed #475569; padding-top: 4px; display: inline-block; min-width: 220px;">
                        <div style="font-size: 12px; font-weight: 800; color: #0f172a;">{{ $letter->circle?->incharge_name ?? 'Incharge Officer' }}</div>
                        <div style="font-size: 10.5px; font-weight: 600; color: #334155;">Circle Incharge / Incharge Officer</div>
                        <div style="font-size: 10px; color: #64748b;">{{ $letter->circle?->name ?? 'Regional Circle' }}</div>
                    </div>
                </td>
                <td style="width: 48%; border: none; vertical-align: top; text-align: right; padding: 0;">
                    <div style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase;">Submitted To:</div>
                    <div style="margin-top: 45px; border-top: 1.5px dashed #475569; padding-top: 4px; display: inline-block; min-width: 220px; text-align: left;">
                        <div style="font-size: 12px; font-weight: 800; color: #0f172a;">The Director General</div>
                        <div style="font-size: 10.5px; font-weight: 600; color: #334155;">National Cyber Crime Investigation Agency (NCCIA)</div>
                        <div style="font-size: 10px; color: #64748b;">Headquarters, Islamabad</div>
                    </div>
                </td>
            </tr>
        </table>
        <div style="margin-top: 24px; text-align: center; font-size: 9px; color: #64748b; border-top: 1px solid #cbd5e1; padding-top: 6px;">
            CONFIDENTIAL &bull; Official D.O. Communication from Regional Circle Command to DG NCCIA Headquarters &bull; Government of Pakistan
        </div>
    </div>
</body>
</html>
