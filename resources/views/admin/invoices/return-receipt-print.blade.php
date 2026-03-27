<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Receipt</title>
    <style>
        @page {
            size: {{ settings('receipt_width', '58mm') }} auto;
            margin: 0;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            margin: 0;
            padding: 8px;
            width: {{ settings('receipt_width', '58mm') }};
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }

        .receipt-header strong {
            display: block;
            font-size: 14px;
        }

        .receipt-header small {
            display: block;
            margin-top: 2px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 2px 0;
            font-size: 10px;
            text-align: left;
        }

        .receipt-table th {
            border-bottom: 1px dashed #000;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .item-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .receipt-total {
            border-top: 1px dashed #000;
            margin-top: 5px;
            padding-top: 3px;
            font-weight: bold;
        }

        .footer {
            border-top: 1px dashed #000;
            margin-top: 10px;
            padding-top: 5px;
            text-align: center;
            font-size: 10px;
        }

        @media print {
            body {
                margin: 0;
                width: {{ settings('receipt_width', '58mm') }};
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
            $pharmacy = \App\Models\Pharmacy::first();
        @endphp
        <h2 style="margin: 0; padding: 0; font-size: 16px;">{{ $pharmacy ? $pharmacy->name : settings('app_name', 'Pharmacy') }}</h2>
        <div style="font-size: 8px; margin-top: 2px;">{{ $pharmacy ? $pharmacy->address : settings('company_address', '123 Main Street, City') }}</div>
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
        <table width="100%" class="item-table">
            <thead>
                <tr>
                    <th width="10%">#</th>
                    <th width="40%">Item</th>
                    <th width="25%" class="text-right">Ret Qty</th>
                    <th width="25%" class="text-right">Raw Total</th>
                </tr>
            </thead>
            <tbody>
            @foreach($returnedItems as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    <b>{{ $item['name'] }}</b><br>
                    <small>{{ $item['category_name'] }}</small>
                </td>
                <td class="text-right">{{ $item['qty'] }}</td>
                <td class="text-right">{{ isset($item['gross_total']) ? number_format($item['gross_total'], 2) : number_format($item['total'], 2) }}</td>
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
                <td><strong>Subtotal:</strong></td>
                <td class="text-right"><strong>{{ number_format($metadata['gross_subtotal'], 2) }}</strong></td>
            </tr>
            @endif
            @if(isset($metadata) && $metadata['total_unit_discount'] > 0)
            <tr>
                <td><small>Product Discounts:</small></td>
                <td class="text-right text-danger"><small>-{{ number_format($metadata['total_unit_discount'], 2) }}</small></td>
            </tr>
            @endif
            @if(isset($metadata) && $metadata['global_discount_clawback'] > 0)
            <tr>
                <td><small>Global Discount:</small></td>
                <td class="text-right text-danger"><small>-{{ number_format($metadata['global_discount_clawback'], 2) }}</small></td>
            </tr>
            @endif
            <tr>
                <td><strong>Total Cash Refund:</strong></td>
                <td class="text-right">
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
        <small>Return Processed Successfully.</small><br>
        <small>Contact: {{ isset($pharmacy) && $pharmacy->phone ? $pharmacy->phone : settings('company_phone', '0300-XXXXXXX') }}</small>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(() => window.close(), 500);
        };
    </script>
</body>
</html>
