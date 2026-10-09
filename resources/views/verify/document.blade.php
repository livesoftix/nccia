<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} Verification — NCCIA</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f2f4f8; display: flex; align-items: flex-start; justify-content: center; min-height: 100vh; padding: 16px; }
        .card { background: #fff; border-radius: 12px; max-width: 640px; width: 100%; box-shadow: 0 12px 40px rgba(0,0,0,0.12); overflow: hidden; }
        .head { background: #015C94; color: #fff; padding: 18px 22px; text-align: center; }
        .head img { height: 54px; }
        .head .org { font-weight: 700; margin-top: 6px; font-size: 13px; }
        .body { padding: 22px; }
        .badge { display: inline-block; padding: 5px 14px; border-radius: 20px; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; background: #e6f7ec; color: #18794e; }
        .doc-type { font-size: 15px; font-weight: 800; text-align: center; margin: 10px 0 14px; color: #0f172a; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #eef0f3; font-size: 14px; }
        .row .k { color: #64748b; font-weight: 600; flex-shrink: 0; }
        .row .v { color: #0f172a; text-align: right; font-weight: 600; word-break: break-word; }
        .sec { margin-top: 18px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; color: #015C94; border-left: 4px solid #015C94; padding-left: 8px; }
        table.devs { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        table.devs th { background: #f1f5f9; text-align: left; padding: 6px 7px; border: 1px solid #cbd5e1; }
        table.devs td { padding: 6px 7px; border: 1px solid #e2e8f0; vertical-align: top; }
        .note { margin-top: 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 13px; white-space: pre-wrap; color: #0f172a; }
        .note .nk { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .foot { padding: 14px 22px; background: #f8fafc; font-size: 12px; color: #64748b; text-align: center; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <img src="{{ asset('images/NCCIA.webp') }}" alt="NCCIA">
            <div class="org">National Cyber Crime Investigation Agency (NCCIA)</div>
        </div>
        <div class="body">
            <div style="text-align:center; margin-bottom: 6px;">
                <span class="badge">✓ Authentic NCCIA Document</span>
            </div>
            <div class="doc-type">{{ $title }}</div>
            @foreach($rows as $row)
                <div class="row"><span class="k">{{ $row['k'] }}</span><span class="v">{{ $row['v'] }}</span></div>
            @endforeach

            @if(!empty($devices))
                <div class="sec">Devices / Evidentiary Media</div>
                <table class="devs">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type / Make</th>
                            <th>IMEI / Serial</th>
                            <th>Seized From</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($devices as $i => $d)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <strong>{{ $d['item_type'] ?: 'Device' }}</strong>
                                    @if($d['make_model'])<div>{{ $d['make_model'] }}</div>@endif
                                    @if($d['storage_capacity'])<div class="muted">{{ $d['storage_capacity'] }}{{ str_contains($d['storage_capacity'], 'GB') ? '' : ' GB' }}</div>@endif
                                    @if($d['condition'])<div class="muted">{{ $d['condition'] }}</div>@endif
                                    @if($d['description'])<div class="muted">{{ $d['description'] }}</div>@endif
                                </td>
                                <td>
                                    @if($d['imei'] && $d['imei2'])
                                        <div>IMEI 1: {{ $d['imei'] }}</div>
                                        <div>IMEI 2: {{ $d['imei2'] }}</div>
                                    @elseif($d['imei'] || $d['imei2'])
                                        <div>IMEI: {{ $d['imei'] ?: $d['imei2'] }}</div>
                                    @endif
                                    @if($d['serial_no'])
                                        <div>SN: {{ $d['serial_no'] }}</div>
                                    @endif
                                    @if(!$d['imei'] && !$d['imei2'] && !$d['serial_no'])
                                        —
                                    @endif
                                </td>
                                <td>{{ $d['seized_from'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($custody))
                <div class="sec">Digital Chain of Custody</div>
                <table class="devs">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Date / Time</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($custody as $i => $c)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $c['from'] }}</td>
                                <td>{{ $c['to'] }}</td>
                                <td>{{ $c['at'] }}</td>
                                <td>{{ $c['remark'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @foreach($notes as $note)
                <div class="note">
                    <div class="nk">{{ $note['k'] }}</div>
                    <div>{{ $note['v'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="foot">This record is stored digitally in NCCIA CMS. Scan confirms authenticity. For queries, contact the concerned circle office.</div>
    </div>
</body>
</html>
