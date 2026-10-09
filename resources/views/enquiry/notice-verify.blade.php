<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Summon Verification — NCCIA</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f2f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
        .card { background: #fff; border-radius: 12px; max-width: 480px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.12); overflow: hidden; }
        .head { background: #015C94; color: #fff; padding: 18px 22px; text-align: center; }
        .head img { height: 54px; }
        .head .org { font-weight: 700; margin-top: 6px; }
        .body { padding: 22px; }
        .badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; }
        .valid { background: #e6f7ec; color: #18794e; }
        .invalid { background: #fdecea; color: #b42318; }
        .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eef0f3; font-size: 14px; }
        .row .k { color: #64748b; font-weight: 600; }
        .row .v { color: #0f172a; text-align: right; font-weight: 600; }
        .foot { padding: 14px 22px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <img src="{{ asset('images/NCCIA.webp') }}" alt="NCCIA">
            <div class="org">National Cyber Crime Investigation Agency (NCCIA)</div>
        </div>
        <div class="body">
            <div style="text-align:center; margin-bottom: 14px;">
                @if($notice->status === 'non_appearance' || $notice->appearance_remarks === 'non_appearance')
                    <span class="badge invalid">❌ Non-Appeared</span>
                @elseif($notice->status === 'appeared' || $notice->appearance_remarks === 'appeared')
                    <span class="badge valid">✅ Appeared</span>
                @elseif(in_array($notice->status, ['issued','unserved']))
                    <span class="badge invalid" style="background:#fff3cd; color:#856404;">⏳ Pending Appearance</span>
                @else
                    <span class="badge valid">✓ Served</span>
                @endif
            </div>

            <div class="row"><span class="k">Summon No.</span><span class="v">{{ $notice->notice_number ?: 'N-'.$notice->id }}</span></div>
            <div class="row"><span class="k">Summon Type</span><span class="v">{{ $notice->notice_type ?: 'Summon' }}</span></div>
            <div class="row"><span class="k">Receiver</span><span class="v">{{ $notice->receiver_name }}</span></div>
            <div class="row"><span class="k">Person Type</span><span class="v">{{ ucfirst($notice->person_type ?? '—') }}</span></div>
            <div class="row"><span class="k">Summon Date</span><span class="v">{{ $notice->notice_date ? $notice->notice_date->format('d/m/Y') : '—' }}</span></div>
            <div class="row"><span class="k">Mode</span><span class="v">{{ ucfirst(str_replace('_',' ', $notice->notice_via ?? '—')) }}</span></div>
            <div class="row"><span class="k">Enquiry No.</span><span class="v">{{ $notice->enquiry->enquiry_number ?? ('#'.$notice->enquiry_id) }}</span></div>
            <div class="row"><span class="k">Complaint No.</span><span class="v">{{ $notice->enquiry->complaint->tracking_no ?? '—' }}</span></div>
            <div class="row"><span class="k">Circle</span><span class="v">{{ $notice->enquiry->complaint->circle->name ?? '—' }}</span></div>
            @if($notice->description)
                <div style="margin-top:12px; font-size:13px; color:#334155;">{{ $notice->description }}</div>
            @endif
        </div>
        <div class="foot">This summon was issued by NCCIA. For queries, please contact the issuing circle office.</div>
    </div>
</body>
</html>
