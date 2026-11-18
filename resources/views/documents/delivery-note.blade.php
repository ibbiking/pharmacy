@php
    $customer = $doc->customer;
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery note {{ $shipment->number }}</title>
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
        .doc-title h1 { margin: 0; font-size: 24px; letter-spacing: .04em; text-transform: uppercase; }
        .grid { display: flex; justify-content: space-between; gap: 24px; margin: 22px 0; }
        .label { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #666; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #555; border-bottom: 1px solid #111; padding: 8px 6px; }
        td { padding: 8px 6px; border-bottom: 1px solid #e6e6ea; vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .sign { margin-top: 72px; display: flex; justify-content: space-between; font-size: 12px; color: #555; }
        .sign div { border-top: 1px solid #999; width: 220px; padding-top: 6px; text-align: center; }
        .toolbar { text-align: center; margin: 16px; font-size: 13px; }
        @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } .toolbar { display: none; } @page { size: A4; margin: 12mm; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print / save as PDF</button></div>
<div class="sheet">
    <div class="head">
        @include('documents._brand', ['business' => $business])
        <div class="doc-title">
            <h1>Delivery note</h1>
            <div><strong>{{ $shipment->number }}</strong></div>
            <div class="muted">{{ $doc->typeLabel() }} {{ $doc->number }} · {{ $doc->date?->format('d M Y') }}</div>
        </div>
    </div>
    <div class="grid">
        <div>
            <div class="label">Deliver to</div>
            <strong>{{ $customer?->name }}</strong>
            @if ($customer?->company)<div>{{ $customer->company }}</div>@endif
            @if ($customer?->address)<div class="muted">{{ $customer->address }}</div>@endif
            @if ($customer?->phone)<div class="muted">{{ $customer->phone }}</div>@endif
        </div>
        <div style="text-align:right">
            <div class="label">Shipment</div>
            <div>{{ \App\Models\Documents\Shipment::STATUSES[$shipment->status] ?? $shipment->status }}@if ($shipment->shipped_at) · shipped {{ $shipment->shipped_at->format('d M Y') }}@endif</div>
            @if ($shipment->carrier)<div>Carrier: <strong>{{ $shipment->carrier }}</strong></div>@endif
            @if ($shipment->tracking_no)<div>Tracking: <strong>{{ $shipment->tracking_no }}</strong></div>@endif
            <div>{{ $shipment->packages }} package{{ $shipment->packages === 1 ? '' : 's' }}@if ($shipment->weight_kg) · {{ $qty($shipment->weight_kg) }} kg @endif</div>
        </div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Item</th><th class="r">Quantity</th><th>Unit</th></tr></thead>
        <tbody>
        @foreach ($doc->lines as $i => $line)
            <tr><td>{{ $i + 1 }}</td><td>{{ $line->product?->product_name ?? $line->description }}</td><td class="r">{{ $qty($line->qty) }}</td><td>{{ $line->unit?->name }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if ($shipment->notes)<p class="muted" style="margin-top:20px; white-space:pre-line">{{ $shipment->notes }}</p>@endif
    <div class="sign"><div>Dispatched by</div><div>Received by (name, signature, date)</div></div>
</div>
</body>
</html>
