<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complaint Receipt Verification — NCCIA</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f2f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
        .card { background: #fff; border-radius: 12px; max-width: 480px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.12); overflow: hidden; }
        .head { background: #015C94; color: #fff; padding: 18px 22px; text-align: center; }
        .head img { height: 54px; }
        .head .org { font-weight: 700; margin-top: 6px; }
        .body { padding: 22px; }
        .badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; background: #e6f7ec; color: #18794e; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #eef0f3; font-size: 14px; }
        .row .k { color: #64748b; font-weight: 600; flex-shrink: 0; }
        .row .v { color: #0f172a; text-align: right; font-weight: 600; word-break: break-word; }
        .foot { padding: 14px 22px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    @php
        $number = $complaint->tracking_no ?: ($complaint->slip_number ?: ('#'.$complaint->id));
        $cnic = $complaint->cnic ?: '—';
        if ($cnic !== '—' && strlen(preg_replace('/\D/', '', $cnic)) >= 10) {
            $digits = preg_replace('/\D/', '', $cnic);
            $cnic = substr($digits, 0, 5) . '-*******-' . substr($digits, -1);
        }
        $status = $complaint->final_status ?: ($complaint->status ?: 'registered');
        $statusLabel = ucwords(str_replace('_', ' ', $status));
    @endphp
    <div class="card">
        <div class="head">
            <img src="{{ asset('images/NCCIA.webp') }}" alt="NCCIA">
            <div class="org">National Cyber Crime Investigation Agency (NCCIA)</div>
        </div>
        <div class="body">
            <div style="text-align:center; margin-bottom: 14px;">
                <span class="badge">✓ Authentic Complaint Receipt</span>
            </div>

            <div class="row"><span class="k">Complaint No.</span><span class="v">{{ $number }}</span></div>
            <div class="row"><span class="k">Complainant</span><span class="v">{{ $complaint->complainant_name ?: '—' }}</span></div>
            <div class="row"><span class="k">CNIC</span><span class="v">{{ $cnic }}</span></div>
            <div class="row"><span class="k">Circle</span><span class="v">{{ $complaint->circle->name ?? '—' }}</span></div>
            <div class="row"><span class="k">Report Date</span><span class="v">{{ $complaint->report_date ? \Illuminate\Support\Carbon::parse($complaint->report_date)->format('d/m/Y') : '—' }}</span></div>
            <div class="row"><span class="k">Offence</span><span class="v">{{ $complaint->offence_type ?: '—' }}</span></div>
            <div class="row"><span class="k">Status</span><span class="v">{{ $statusLabel }}</span></div>
        </div>
        <div class="foot">This receipt was issued by NCCIA. For queries, please contact the concerned circle office.</div>
    </div>
</body>
</html>
