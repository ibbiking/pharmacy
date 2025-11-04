<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ settings('pharmacy_name', 'Pharmacy') }}</title>
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

        .text-right { text-align: right; }
        .text-center { text-align: center; }

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

        .error-message {
            color: red;
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
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
    <div class="receipt-header">
        <strong>{{ settings('pharmacy_name', 'Your Pharmacy Name') }}</strong>
        <small>{{ settings('pharmacy_address', 'Address here...') }}</small>
        <small>Date: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</small>
    </div>

    @if(isset($error))
        <div class="error-message">{{ $error }}</div>
    @endif

    <table class="receipt-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Disc</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cartItems as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="item-name">{{ $item['name'] }}</td>
                    <td class="text-center">{{ $item['qty'] }}</td>
                    <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                    <td class="text-right">
                        @if($item['discount_selected_type'] === 'percent')
                            {{ number_format($item['discount_percent'], 2) }}%
                        @else
                            {{ number_format($item['discount_amount'], 2) }}
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
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                </tr>
                @if($invoiceDiscount > 0)
                <tr>
                    <td>Invoice Discount:</td>
                    <td class="text-right">-{{ number_format($invoiceDiscount, 2) }}</td>
                </tr>
                @endif
                <tr>
                    <td><strong>Grand Total:</strong></td>
                    <td class="text-right"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                </tr>
            </table>
        </div>
    @endif

    <div class="footer">
        <small>Thank you for your purchase!</small><br>
        <small>{{ settings('pharmacy_contact', 'Contact: 0300-XXXXXXX') }}</small><br>
        <small>Powered by Sublime POS</small>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(() => window.close(), 500);
        };
    </script>
</body>
</html>