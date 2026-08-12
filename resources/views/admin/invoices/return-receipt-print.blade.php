<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Receipt</title>
    @include('admin.partials._receipt-styles')
</head>
<body>
    @php
        $business = $business ?? null;
        if (!$business) {
            $businessId = session('business_id');
            $business = $businessId ? \App\Models\Business::find($businessId) : null;
        }
    @endphp

    <div id="receipt" class="pmx-receipt">
        <div class="pmx-header">
            <h1 class="pmx-brand">{{ $business ? $business->name : settings('app_name', 'Business') }}</h1>
            <div class="pmx-brand-line">{{ $business ? $business->address : settings('company_address', '123 Main Street, City') }}</div>
            <div class="pmx-doc-title">Return Receipt</div>
        </div>

        <div class="pmx-meta">
            @if(isset($return_no))
            <div class="pmx-row">
                <span class="pmx-label">Return #</span>
                <span class="pmx-value">{{ $return_no }}</span>
            </div>
            @endif
            <div class="pmx-row">
                <span class="pmx-label">Invoice #</span>
                <span class="pmx-value">{{ $invoice_no }}</span>
            </div>
            <div class="pmx-row">
                <span class="pmx-label">Date</span>
                <span class="pmx-value">{{ date('d-M-y g:ia') }}</span>
            </div>
        </div>

        <div class="pmx-divider"></div>

        <table class="pmx-items">
            <thead>
                <tr>
                    <th class="col-sr">#</th>
                    <th class="col-item">Item</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-price">Price</th>
                    <th class="col-disc">Disc</th>
                    <th class="col-total">Total</th>
                </tr>
            </thead>
            <tbody>
            @foreach($returnedItems as $item)
            <tr>
                <td class="col-sr">{{ $loop->iteration }}</td>
                <td class="col-item">
                    <span class="item-name">
                        {{ preg_replace('/\s*-\s*/', '-', $item['name']) }}{{ !empty($item['strength']) ? '-' . $item['strength'] : '' }}
                    </span>
                    @if(!empty($item['product_type']))
                    <span class="item-type">{{ $item['product_type'] }}</span>
                    @endif
                </td>
                <td class="col-qty">{{ $item['qty'] }}</td>
                <td class="col-price">{{ number_format($item['price'], 2) }}</td>
                <td class="col-disc">
                    @if(
                        (isset($item['discount_selected_type']) && $item['discount_selected_type'] === 'percent' && ($item['discount_percent'] ?? 0) > 0) ||
                        (isset($item['discount_selected_type']) && $item['discount_selected_type'] === 'amount' && ($item['discount_amount'] ?? 0) > 0)
                    )
                        @if($item['discount_selected_type'] === 'percent')
                        {{ number_format($item['discount_percent'], 2) }}%
                        @else
                        {{ number_format($item['discount_amount'], 2) }}
                        @endif
                    @else
                    &ndash;
                    @endif
                </td>
                <td class="col-total">{{ number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>

        @php
            $salesTaxes = array_values(array_filter($taxBreakdown ?? [], fn($t) => $t['tax_id'] !== null));
            $gstTaxes = array_values(array_filter($taxBreakdown ?? [], fn($t) => $t['tax_id'] === null));
        @endphp

        <div class="pmx-divider"></div>

        <div class="pmx-totals">
            @if(isset($metadata) && isset($metadata['gross_subtotal']) && $metadata['gross_subtotal'] > 0)
            <div class="pmx-row">
                <span class="pmx-label">Subtotal</span>
                <span class="pmx-value">{{ number_format($metadata['gross_subtotal'], 2) }}</span>
            </div>
            @endif

            @if(isset($metadata) && $metadata['total_unit_discount'] > 0)
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label">Product Discounts</span>
                <span class="pmx-value">-{{ number_format($metadata['total_unit_discount'], 2) }}</span>
            </div>
            @endif

            @if(isset($metadata) && $metadata['global_discount_clawback'] > 0)
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label">Global Discount</span>
                <span class="pmx-value">-{{ number_format($metadata['global_discount_clawback'], 2) }}</span>
            </div>
            @endif

            @foreach($salesTaxes as $tax)
            <div class="pmx-row">
                <span class="pmx-label">{{ $tax['name'] }} Refunded ({{ number_format($tax['rate'], 2) }}%)</span>
                <span class="pmx-value">+{{ number_format($tax['amount'], 2) }}</span>
            </div>
            @endforeach

            <div class="pmx-row pmx-row--grand">
                <span class="pmx-label">Total Cash Refund</span>
                <span class="pmx-value">
                    @php
                        $clawback = isset($metadata) ? $metadata['global_discount_clawback'] : 0;
                        $grandRefund = $totalReturn - $clawback;
                    @endphp
                    {{ number_format($grandRefund, 2) }}
                </span>
            </div>
        </div>

        @if(!empty($gstTaxes))
        <div class="pmx-divider"></div>

        <div class="pmx-totals">
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label"><strong>GST Disclosure (already included above, not an extra refund)</strong></span>
                <span class="pmx-value"></span>
            </div>
            @foreach($gstTaxes as $tax)
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label">{{ $tax['name'] }} ({{ number_format($tax['rate'], 2) }}%)</span>
                <span class="pmx-value">{{ number_format($tax['amount'], 2) }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <div class="pmx-divider"></div>

        <div class="pmx-footer">
            @if($business && $business->note)
            <div>{{ $business->note }}</div>
            @endif
            <div class="pmx-thanks">Return Processed Successfully</div>
            <div>Contact: {{ $business && $business->phone ? $business->phone : settings('company_phone', '0300-XXXXXXX') }}</div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(() => window.close(), 500);
        };
    </script>
</body>
</html>
