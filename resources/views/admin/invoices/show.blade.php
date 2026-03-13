@extends('admin.layouts.app')

@section('content')
<div class="container">

    <h4>Invoice: {{ $invoice->invoice_no }}</h4>

    <p><strong>Date:</strong> {{ $invoice->created_at }}</p>
    <p><strong>Grand Total:</strong> {{ number_format($invoice->grand_total, 2) }}</p>
    <p><strong>Cash Received:</strong> {{ number_format($invoice->cash_received, 2) }}</p>
    <p><strong>Change:</strong> {{ number_format($invoice->change_return, 2) }}</p>
    <form action="{{ route('invoices.return', $invoice->invoice_no) }}" method="POST" class="mb-4">
        @csrf
        <input type="text" name="reason" placeholder="Reason for return (optional)" class="form-control mb-2">
        <button type="submit" class="btn btn-danger">
            Return Full Invoice
        </button>
    </form>
    <hr>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Discount (Rate)</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->product->product_name ?? 'N/A' }}</td>
                    <td><span class="badge badge-info text-white">{{ $item->category->name ?? 'N/A' }}</span></td>
                    <td>
                        {{ $item->net_qty }}
                        @if($item->qty > $item->net_qty)
                            <br><small class="text-danger">(Returned: {{ $item->qty - $item->net_qty }})</small>
                        @endif
                    </td>
                    <td>{{ number_format($item->price, 2) }}</td>
                    <td>
                        {{ number_format($item->discount_amount, 2) }} 
                        ({{ $item->discount_type == 'percent' ? $item->discount_value . '%' : $item->discount_value }})
                    </td>
                    <td>{{ number_format($item->row_total, 2) }}</td>
                    <td>
                        @if($item->net_qty > 0)
                            <form action="{{ route('invoices.return-product', ['invoice_no' => $invoice->invoice_no, 'item_id' => $item->id]) }}" method="POST">
                                @csrf
                                <input type="number" name="return_qty" max="{{ $item->net_qty }}" min="1" style="width: 60px;" required>
                                <button type="submit" class="btn btn-sm btn-warning">Return</button>
                            </form>
                        @else
                            <span class="badge badge-danger text-white">Fully Returned</span>
                        @endif
                    </td>
                    <td>Price: {{ number_format($item->price, 2) }}</td> <!-- show price for reference -->
                </tr>
            @endforeach
        </tbody>
    </table>

</div>
@endsection