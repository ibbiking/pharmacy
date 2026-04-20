<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ isset($business) && $business ? $business->name : settings('app_name', 'Business') }}</title>
    <style>
        @page {
            size: 3in auto; /* Thermal standard */
            margin: 0;
        }

        body {
            font-family: 'Consolas', 'Liberation Mono', 'DejaVu Sans Mono', 'Courier New', monospace;
            font-size: 10.5px;
            font-weight: 700; /* Keep all text dark on thermal print */
            color: #000;
            margin: 0;
            padding: 4px 8px 4px 4px;
            width: 3in;
            box-sizing: border-box;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }

        .receipt-header h2 {
            margin: 0;
            padding: 0;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .receipt-header small {
            display: block;
            font-size: 10px;
        }

        .receipt-table {
            width: 100%;
            table-layout: fixed; /* Rigid boundaries to prevent wrapping from ruining alignments */
            border-collapse: collapse;
        }

        /* Enforce structured alignment explicitly */
        .receipt-table th,
        .receipt-table td {
            padding: 3px 1px;
            font-size: 9.6px;
            vertical-align: top;
            font-weight: 700;
        }

        .receipt-table th {
            border-bottom: 2px dashed #000;
        }

        .col-sr { width: 10%; text-align: center; padding-left: 0; white-space: nowrap; }
        .col-item { width: 35%; text-align: left; }
        .col-qty { width: 14%; text-align: left; padding-left: 2px !important; }
        .col-price { width: 16%; text-align: right; padding-right: 3px !important; }
        .col-disc { width: 11%; text-align: left; padding-left: 2px !important; font-size: 8px; }
        .col-total { width: 14%; text-align: right; padding-right: 10px !important; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .item-name {
            word-wrap: break-word;
            white-space: normal;
            display: block;
            line-height: 1.1;
        }

        .receipt-table td.col-sr {
            font-size: 11px;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .receipt-table th.col-total {
            padding-right: 10px !important;
        }

        .receipt-content {
            padding-right: 2px;
        }

        .receipt-total {
            border-top: 2px dashed #000;
            margin-top: 5px;
            padding-top: 5px;
        }

        .receipt-total table td {
            font-size: 10.5px;
            padding: 2px 0;
            font-weight: 700;
        }

        .receipt-total .grand-total {
            font-size: 13px;
        }

        .footer {
            border-top: 2px dashed #000;
            margin-top: 10px;
            padding-top: 5px;
            text-align: center;
            font-size: 10px;
        }

        @media print {
            body {
                margin: 0;
                width: 3in;
            }
            .receipt-table {
                width: 100% !important;
                table-layout: fixed !important;
            }
            .receipt-table th,
            .receipt-table td {
                overflow: visible !important;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div id="receipt" class="receipt-content">
    <div class="receipt-header text-center" style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px dashed #000;">
        @php
            $business = $business ?? null;
            if (!$business) {
                $businessId = session('business_id');
                $business = $businessId ? \App\Models\Business::find($businessId) : null;
            }
        @endphp
        <h2 style="margin: 0; padding: 0; font-size: 16px;">{{ $business ? $business->name : settings('app_name', 'Business') }}</h2>
        <div style="font-size: 8px; margin-top: 0;">{{ $business ? $business->address : settings('company_address', '123 Main Street, City') }}</div>
        <div style="margin-top: 5px;"><strong>SALE RECEIPT</strong></div>
        <div style="font-size: 11px;">{{ date('d-M-y g:ia', strtotime($invoice_date ?? now())) }}</div>
        @if(isset($invoice_no))
        <div style="font-size: 11px;"><strong>Invoice #: {{ $invoice_no }}</strong></div>
        @endif
    </div>

    @if(isset($error))
    <div class="error-message">{{ $error }}</div>
    @endif

    <table class="receipt-table">
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
                    <span class="item-name">
                        {{ preg_replace('/\s*-\s*/', '-', $item['name']) }}
                    </span>
                    @if(!empty($item['product_type']))
                        <div style="font-size: 9px; font-weight: bold; margin: 0; line-height: 1;">{{ $item['product_type'] }}</div>
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
                        {{ number_format($item['discount_amount'], 2) }} RS
                        @endif
                    @else
                        -
                    @endif
                </td>
                <td class="col-total">{{ number_format($item['total'], 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">No items in cart</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if(count($cartItems) > 0)
    <div class="receipt-total">
        <table width="100%">
            @if(isset($grossSubtotal) && $grossSubtotal > 0)
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">Subtotal:</td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">{{ number_format($grossSubtotal, 2) }}</td>
            </tr>
            @else
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">Subtotal:</td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">{{ number_format($subtotal, 2) }}</td>
            </tr>
            @endif
            @if(isset($totalItemDiscount) && $totalItemDiscount > 0)
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">Product Discounts:</td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">-{{ number_format($totalItemDiscount, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">Net Subtotal:</td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">{{ number_format($subtotal, 2) }}</td>
            </tr>
            @endif
            @if($invoiceDiscount > 0)
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">
                    Invoice Discount:
                    @if($invoiceDiscountType === 'percent')
                    ({{ number_format($invoiceDiscountValue, 2) }}%)
                    @else
                    ({{ number_format($invoiceDiscountValue, 2) }} RS)
                    @endif
                </td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">-{{ number_format($invoiceDiscount, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left; font-size: 13px;"><strong>Grand Total:</strong></td>
                <td style="width: 24%; text-align: right; padding-right: 10px; font-size: 13px;"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
            </tr>
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">Cash Received:</td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">{{ number_format($cashReceived ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;">Change Return:</td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">{{ number_format($changeReturn ?? 0, 2) }}</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="footer" style="border-top: 1px dashed #000; padding-top: 5px; margin-top: 5px;">
        @if($business && $business->note)
        <small>{{ $business->note }}</small><br>
        @endif
        <small>Thank you for your purchase!</small><br>
        <small>Visit Again</small><br>
        <small>Contact: {{ $business && $business->phone ? $business->phone : settings('company_phone', '0300-XXXXXXX') }}</small>
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