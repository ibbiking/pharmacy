<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ \App\Models\Pharmacy::first() ? \App\Models\Pharmacy::first()->name : 'Default Pharmacy Name' }}</title>
    <style>
        @page {
            size: 3in auto; /* Thermal standard */
            margin: 0;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            font-weight: bold; /* Dark and readable */
            color: #000;
            margin: 0;
            padding: 5px;
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
            font-size: 10px;
            vertical-align: top;
            font-weight: bold;
        }

        .receipt-table th {
            border-bottom: 2px dashed #000;
        }

        .col-sr { width: 10%; text-align: left; padding-left: 5px; }
        .col-item { width: 34%; text-align: left; }
        .col-qty { width: 12%; text-align: center; }
        .col-price { width: 16%; text-align: right; }
        .col-disc { width: 12%; text-align: right; }
        .col-total { width: 16%; text-align: right; }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .item-name {
            word-wrap: break-word;
            white-space: normal;
            display: block;
            line-height: 1.1;
        }

        .receipt-total {
            border-top: 2px dashed #000;
            margin-top: 5px;
            padding-top: 5px;
        }

        .receipt-total table td {
            font-size: 11px;
            padding: 2px 0;
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
            $businessId = session('business_id');
            $business = $businessId ? \App\Models\Business::find($businessId) : null;
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
                        {{ $item['name'] }}{{ !empty($item['strength']) ? ' (' . $item['strength'] . ')' : '' }}
                    </span>
                    @if(!empty($item['product_type']))
                        <div style="font-size: 9px; font-weight: bold; margin: 0; line-height: 1;">{{ $item['product_type'] }}</div>
                    @endif
                    @if(!empty($item['category_name']))
                        <div style="font-size: 9px; font-weight: bold; margin: 0; line-height: 1;">{{ $item['category_name'] }}</div>
                    @endif
                </td>
                <td class="text-center">{{ $item['qty'] }}</td>
                <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                <td class="text-right">
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
                <td class="text-right">{{ number_format($item['total'], 2) }}</td>
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
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">Subtotal:</td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">{{ number_format($grossSubtotal, 2) }}</td>
            </tr>
            @else
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">Subtotal:</td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">{{ number_format($subtotal, 2) }}</td>
            </tr>
            @endif
            @if(isset($totalItemDiscount) && $totalItemDiscount > 0)
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">Product Discounts:</td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">-{{ number_format($totalItemDiscount, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">Net Subtotal:</td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">{{ number_format($subtotal, 2) }}</td>
            </tr>
            @endif
            @if($invoiceDiscount > 0)
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">
                    Invoice Discount:
                    @if($invoiceDiscountType === 'percent')
                    ({{ number_format($invoiceDiscountValue, 2) }}%)
                    @else
                    ({{ number_format($invoiceDiscountValue, 2) }} RS)
                    @endif
                </td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">-{{ number_format($invoiceDiscount, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left; font-size: 13px;"><strong>Grand Total:</strong></td>
                <td style="width: 25%; text-align: right; padding-right: 5px; font-size: 13px;"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
            </tr>
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">Cash Received:</td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">{{ number_format($cashReceived ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td style="width: 40%;"></td>
                <td style="width: 35%; text-align: left;">Change Return:</td>
                <td style="width: 25%; text-align: right; padding-right: 5px;">{{ number_format($changeReturn ?? 0, 2) }}</td>
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