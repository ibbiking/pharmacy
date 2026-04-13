<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Receipt</title>
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
        .col-item { width: 45%; text-align: left; }
        .col-qty { width: 20%; text-align: center; }
        .col-total { width: 25%; text-align: right; padding-right: 10px !important; }

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
        <h2 style="margin: 0; padding: 0; font-size: 16px;">{{ isset($business) && $business ? $business->name : settings('app_name', 'Business') }}</h2>
        <div style="font-size: 8px; margin-top: 2px;">{{ isset($business) && $business && !empty($business->address) && $business->address != 'N/A' ? $business->address : settings('company_address', '123 Main Street, City') }}</div>
        <div style="margin-top: 5px;"><strong>RETURN RECEIPT</strong></div>
        <div style="font-size: 11px;">Date: {{ date('d-M-y g:ia') }}</div>
        @if(isset($return_no))
        <div style="font-size: 11px;"><strong>{{ $return_no }}</strong></div>
        @endif
        <div style="font-size: 11px;">{{ $invoice_no }}</div>
        @if(!empty($invoice_date))
        <div style="font-size: 11px;">Inv Date: {{ $invoice_date }}</div>
        @endif
    </div>

    <div class="receipt-body">
        <table class="receipt-table">
            <thead>
                <tr>
                    <th class="col-sr">#</th>
                    <th class="col-item">Item</th>
                    <th class="col-qty">Ret Qty</th>
                    <th class="col-total">Raw Total</th>
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
                    <div style="font-size: 9px; font-weight: 700; margin: 0; line-height: 1;">{{ $item['product_type'] }}</div>
                    @endif
                    <div style="font-size: 9px; font-weight: bold; margin: 0; line-height: 1;">{{ $item['category_name'] }}</div>
                </td>
                <td class="col-qty">{{ $item['qty'] }}</td>
                <td class="col-total">{{ isset($item['gross_total']) ? number_format($item['gross_total'], 2) : number_format($item['total'], 2) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <hr style="border-top: 1px dashed #000; margin: 10px 0;">

    <div class="receipt-total">
        <table width="100%">
            @if(isset($metadata) && isset($metadata['gross_subtotal']) && $metadata['gross_subtotal'] > 0)
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;"><strong>Subtotal:</strong></td>
                <td style="width: 24%; text-align: right; padding-right: 10px;"><strong>{{ number_format($metadata['gross_subtotal'], 2) }}</strong></td>
            </tr>
            @endif
            @if(isset($metadata) && $metadata['total_unit_discount'] > 0)
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;"><small>Product Discounts:</small></td>
                <td style="width: 24%; text-align: right; padding-right: 10px;" class="text-danger"><small>-{{ number_format($metadata['total_unit_discount'], 2) }}</small></td>
            </tr>
            @endif
            @if(isset($metadata) && $metadata['global_discount_clawback'] > 0)
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;"><small>Global Discount:</small></td>
                <td style="width: 24%; text-align: right; padding-right: 10px;" class="text-danger"><small>-{{ number_format($metadata['global_discount_clawback'], 2) }}</small></td>
            </tr>
            @endif
            <tr>
                <td style="width: 42%;"></td>
                <td style="width: 34%; text-align: left;"><strong>Total Cash Refund:</strong></td>
                <td style="width: 24%; text-align: right; padding-right: 10px;">
                    <strong>
                        @php 
                            $clawback = isset($metadata) ? $metadata['global_discount_clawback'] : 0;
                            $grandRefund = $totalReturn - $clawback;
                        @endphp
                        {{ number_format($grandRefund, 2) }}
                    </strong>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        @if(isset($business) && $business && $business->note)
        <small>{{ $business->note }}</small><br>
        @endif
        <small>Return Processed Successfully.</small><br>
        <small>Contact: {{ isset($business) && $business && $business->phone && $business->phone != 'N/A' ? $business->phone : settings('company_phone', '0300-XXXXXXX') }}</small>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(() => window.close(), 500);
        };
    </script>
</body>
</html>
