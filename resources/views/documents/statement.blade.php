@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Statement · {{ $party->name }}</title>
    <style>
        body { margin: 0; font-family: "Inter", system-ui, sans-serif; color: #111; background: #f4f4f7; }
        .sheet { background: #fff; margin: 24px auto; padding: 40px; max-width: 210mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .brand { display: flex; gap: 14px; align-items: center; }
        .logo { max-height: 56px; } .badge-logo { display: inline-grid; place-items: center; width: 52px; height: 52px; border-radius: 14px; color: #fff; font-weight: 800; }
        .biz-name { font-weight: 800; font-size: 18px; } .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 16px; margin-bottom: 18px; }
        h1 { margin: 0; font-size: 22px; text-transform: uppercase; letter-spacing: .04em; text-align: right; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 14px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; color: #555; border-bottom: 1px solid #111; padding: 7px 6px; }
        td { padding: 7px 6px; border-bottom: 1px solid #eee; } .r { text-align: right; white-space: nowrap; }
        .toolbar { text-align: center; margin: 16px; } @media print { .toolbar { display: none; } body { background: #fff; } .sheet { box-shadow: none; margin: 0; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
<div class="sheet">
    <div class="head">
        @include('documents._brand', ['business' => $business])
        <div><h1>Statement</h1><div class="muted" style="text-align:right">{{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div></div>
    </div>
    <div><span class="muted">{{ $customer ? 'Customer' : 'Supplier' }}</span><br><strong>{{ $party->name }}</strong> @if ($party->company)· {{ $party->company }}@endif</div>
    <table>
        <thead><tr><th>Date</th><th>Reference</th><th>Details</th><th class="r">Amount</th><th class="r">Balance</th></tr></thead>
        <tbody>
            <tr><td colspan="4"><em>Opening balance</em></td><td class="r">{{ $money($opening) }}</td></tr>
            @foreach ($rows as $r)
                <tr><td>{{ $r['date']->format('d M Y') }}</td><td>{{ $r['ref'] }}</td><td>{{ $r['what'] }}</td><td class="r">{{ $money($r['amount']) }}</td><td class="r">{{ $money($r['balance']) }}</td></tr>
            @endforeach
            <tr><td colspan="4"><strong>{{ $customer ? 'Balance due to us' : 'Balance we owe' }}</strong></td><td class="r"><strong>{{ $currency }} {{ $money($closing) }}</strong></td></tr>
        </tbody>
    </table>
</div>
</body>
</html>
