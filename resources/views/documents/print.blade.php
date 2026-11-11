@php
    $money = fn ($v) => number_format((float) $v, 2);
    $party = $doc->party();
    $thermal = $format === 'thermal';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doc->typeLabel() }} {{ $doc->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Inter", system-ui, -apple-system, "Segoe UI", sans-serif; color: #111; background: #f4f4f7; }
        .sheet { background: #fff; margin: 24px auto; padding: 40px 44px; width: 210mm; min-height: 297mm; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .brand { display: flex; gap: 14px; align-items: center; }
        .logo { max-height: 64px; max-width: 180px; object-fit: contain; }
        .badge-logo { display: inline-grid; place-items: center; width: 56px; height: 56px; border-radius: 14px; color: #fff; font-weight: 800; font-size: 20px; }
        .biz-name { font-weight: 800; font-size: 18px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 18px; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 26px; letter-spacing: .04em; text-transform: uppercase; }
        .doc-title .no { font-weight: 700; font-size: 15px; }
        .grid { display: flex; justify-content: space-between; gap: 24px; margin: 22px 0; }
        .label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #666; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #555; border-bottom: 1px solid #111; padding: 8px 6px; }
        td { padding: 8px 6px; border-bottom: 1px solid #e6e6ea; vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: 300px; margin-top: 14px; font-size: 13px; }
        .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
        .totals .grand { border-top: 2px solid #111; margin-top: 4px; padding-top: 8px; font-weight: 800; font-size: 16px; }
        .notes { margin-top: 28px; font-size: 12px; color: #333; white-space: pre-line; }
        .sign { margin-top: 64px; display: flex; justify-content: space-between; font-size: 12px; color: #555; }
        .sign div { border-top: 1px solid #999; width: 220px; padding-top: 6px; text-align: center; }
        .toolbar { text-align: center; margin: 16px; font-size: 13px; }
        .toolbar a, .toolbar button { margin: 0 4px; }
        /* Thermal (58/80 mm roll) */
        body.thermal .sheet { width: 80mm; min-height: 0; padding: 10px 8px; font-size: 11px; }
        body.thermal .head, body.thermal .grid { display: block; }
        body.thermal .doc-title { text-align: left; margin-top: 8px; }
        body.thermal .doc-title h1 { font-size: 15px; }
        body.thermal table { font-size: 11px; }
        body.thermal .hide-thermal { display: none; }
        body.thermal .totals { width: 100%; }
        body.thermal .logo { max-height: 40px; }
        body.thermal .sign { display: none; }
        @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; } .toolbar { display: none; } @page { size: {{ $thermal ? '80mm auto' : 'A4' }}; margin: {{ $thermal ? '0' : '12mm' }}; } body:not(.thermal) .sheet { width: auto; min-height: 0; padding: 0; } }
    </style>
</head>
<body class="{{ $thermal ? 'thermal' : '' }}">
<div class="toolbar">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
    <a href="?format={{ $thermal ? 'a4' : 'thermal' }}">{{ $thermal ? 'A4 layout' : 'Thermal (80 mm) layout' }}</a>
</div>
<div class="sheet">
    <div class="head">
        @include('documents._brand', ['business' => $business])
        <div class="doc-title">
            <h1>{{ $doc->typeLabel() }}</h1>
            <div class="no">{{ $doc->number }}</div>
            <div class="muted">Date: {{ $doc->date?->format('d M Y') }}</div>
            @if ($doc->due_date)<div class="muted">{{ $doc->type === 'quotation' ? 'Valid until' : 'Due' }}: {{ $doc->due_date->format('d M Y') }}</div>@endif
            @if ($doc->reference)<div class="muted">Ref: {{ $doc->reference }}</div>@endif
        </div>
    </div>
    <div class="grid">
        <div>
            <div class="label">{{ $doc->isSales() ? 'Bill to' : 'Supplier' }}</div>
            <strong>{{ $party?->name }}</strong>
            @if ($party?->company)<div>{{ $party->company }}</div>@endif
            @if ($party?->address)<div class="muted">{{ $party->address }}</div>@endif
            @if ($party?->phone)<div class="muted">{{ $party->phone }}</div>@endif
            @if ($party?->tax_number)<div class="muted">Tax no: {{ $party->tax_number }}</div>@endif
        </div>
        @if (in_array($doc->type, ['sales_invoice', 'bill'], true) && $doc->status !== 'draft')
            <div class="hide-thermal" style="text-align:right">
                <div class="label">Balance due</div>
                <div style="font-size:22px;font-weight:800">{{ $currency }} {{ $money($doc->balance) }}</div>
                <div class="muted">Status: {{ ucfirst(str_replace('_', ' ', $doc->status)) }}</div>
            </div>
        @endif
    </div>
    <table>
        <thead><tr><th>#</th><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r hide-thermal">Disc.</th><th class="r hide-thermal">Tax</th><th class="r">Amount</th></tr></thead>
        <tbody>
        @foreach ($doc->lines as $i => $l)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $l->product?->product_name }}</strong>@if ($l->description)<div class="muted">{{ $l->description }}</div>@endif</td>
                <td class="r">{{ rtrim(rtrim(number_format($l->qty, 2), '0'), '.') }} {{ $l->unit?->name }}</td>
                <td class="r">{{ $money($l->unit_price) }}</td>
                <td class="r hide-thermal">{{ $l->discount_percent > 0 ? rtrim(rtrim(number_format($l->discount_percent, 2), '0'), '.').'%' : '—' }}</td>
                <td class="r hide-thermal">{{ $l->tax_amount > 0 ? ($l->tax?->name.' '.$money($l->tax_amount)) : '—' }}</td>
                <td class="r">{{ $money($l->line_total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="totals">
        <div><span>Subtotal</span><span>{{ $money($doc->subtotal) }}</span></div>
        @if ($doc->discount_total > 0)<div><span>Discount</span><span>−{{ $money($doc->discount_total) }}</span></div>@endif
        @foreach ($doc->lines->where('tax_amount', '>', 0)->groupBy(fn ($l) => $l->tax?->name.' '.rtrim(rtrim(number_format($l->tax_rate, 2), '0'), '.').'%') as $name => $ls)
            <div><span>{{ $name }}</span><span>{{ $money($ls->sum('tax_amount')) }}</span></div>
        @endforeach
        <div class="grand"><span>Total</span><span>{{ $currency }} {{ $money($doc->total) }}</span></div>
        @if ($doc->paid > 0)<div><span>Paid / credited</span><span>−{{ $money($doc->paid) }}</span></div><div><strong>Balance</strong><strong>{{ $money($doc->balance) }}</strong></div>@endif
    </div>
    @if ($doc->notes)<div class="notes"><div class="label">Notes</div>{{ $doc->notes }}</div>@endif
    @if ($doc->terms)<div class="notes"><div class="label">Terms</div>{{ $doc->terms }}</div>@endif
    <div class="sign"><div>Prepared by</div><div>{{ $doc->isSales() ? 'Received by' : 'Approved by' }}</div></div>
</div>
</body>
</html>
