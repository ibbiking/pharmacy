<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ isset($business) && $business ? $business->name : settings('app_name', 'Business') }}</title>
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
            <div class="pmx-doc-title">Sale Receipt</div>
        </div>

        <div class="pmx-meta">
            @if(isset($invoice_no))
            <div class="pmx-row">
                <span class="pmx-label">Invoice #</span>
                <span class="pmx-value">{{ $invoice_no }}</span>
            </div>
            @endif
            <div class="pmx-row">
                <span class="pmx-label">Date</span>
                <span class="pmx-value">{{ date('d-M-y g:ia', strtotime($invoice_date ?? now())) }}</span>
            </div>
        </div>

        <div class="pmx-divider"></div>

        @if(isset($error))
        <div class="error-message">{{ $error }}</div>
        @endif

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
                @forelse($cartItems as $index => $item)
                <tr>
                    <td class="col-sr">{{ $index + 1 }}</td>
                    <td class="col-item">
                        <span class="item-name">{{ preg_replace('/\s*-\s*/', '-', $item['name']) }}</span>
                        @if(!empty($item['product_type']))
                            <span class="item-type">{{ $item['product_type'] }}</span>
                        @endif
                    </td>
                    <td class="col-qty">{{ $item['qty'] }}</td>
                    <td class="col-price">{{ number_format($item['price'], 2) }}</td>
                    <td class="col-disc">
                        @if(
                            ($item['discount_selected_type'] === 'percent' && ($item['discount_percent'] ?? 0) > 0) ||
                            ($item['discount_selected_type'] === 'amount' && ($item['discount_amount'] ?? 0) > 0)
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
                @empty
                <tr>
                    <td colspan="6" class="pmx-empty">No items in cart</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @php
            $salesTaxes = array_values(array_filter($taxBreakdown ?? [], fn($t) => $t['tax_id'] !== null));
            $gstTaxes = array_values(array_filter($taxBreakdown ?? [], fn($t) => $t['tax_id'] === null));
            $salesTaxTotal = array_sum(array_column($salesTaxes, 'amount'));
        @endphp

        @if(count($cartItems) > 0)
        <div class="pmx-divider"></div>

        <div class="pmx-totals">
            @if(isset($grossSubtotal) && $grossSubtotal > 0)
            <div class="pmx-row">
                <span class="pmx-label">Subtotal</span>
                <span class="pmx-value">{{ number_format($grossSubtotal, 2) }}</span>
            </div>
            @else
            <div class="pmx-row">
                <span class="pmx-label">Subtotal</span>
                <span class="pmx-value">{{ number_format($subtotal, 2) }}</span>
            </div>
            @endif

            @if(isset($totalItemDiscount) && $totalItemDiscount > 0)
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label">Product Discounts</span>
                <span class="pmx-value">-{{ number_format($totalItemDiscount, 2) }}</span>
            </div>
            <div class="pmx-row">
                <span class="pmx-label">Net Subtotal</span>
                <span class="pmx-value">{{ number_format($subtotal, 2) }}</span>
            </div>
            @endif

            @if($invoiceDiscount > 0)
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label">
                    Invoice Discount
                    @if($invoiceDiscountType === 'percent')
                        ({{ number_format($invoiceDiscountValue, 2) }}%)
                    @else
                        ({{ number_format($invoiceDiscountValue, 2) }})
                    @endif
                </span>
                <span class="pmx-value">-{{ number_format($invoiceDiscount, 2) }}</span>
            </div>
            @endif

            @foreach($salesTaxes as $tax)
            <div class="pmx-row">
                <span class="pmx-label">{{ $tax['name'] }} ({{ number_format($tax['rate'], 2) }}%)</span>
                <span class="pmx-value">+{{ number_format($tax['amount'], 2) }}</span>
            </div>
            @endforeach

            <div class="pmx-row pmx-row--grand">
                <span class="pmx-label">Grand Total</span>
                <span class="pmx-value">{{ number_format($grandTotal, 2) }}</span>
            </div>

            <div class="pmx-row" style="margin-top: 6px;">
                <span class="pmx-label">Cash Received</span>
                <span class="pmx-value">{{ number_format($cashReceived ?? 0, 2) }}</span>
            </div>
            <div class="pmx-row">
                <span class="pmx-label">Change Return</span>
                <span class="pmx-value">{{ number_format($changeReturn ?? 0, 2) }}</span>
            </div>
        </div>
        @endif

        @if(!empty($gstTaxes))
        <div class="pmx-divider"></div>

        <div class="pmx-totals">
            <div class="pmx-row pmx-row--muted">
                <span class="pmx-label"><strong>GST Disclosure (already included in price above, not an extra charge)</strong></span>
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
            <div class="pmx-thanks">Thank you for your purchase!</div>
            <div>Please visit again</div>
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
