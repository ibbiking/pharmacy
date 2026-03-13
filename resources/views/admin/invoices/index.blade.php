@extends('admin.layouts.app')

@section('content')
<div class="container">
    <h4>Invoices</h4>

    <form method="GET" class="mb-3">
        <input type="text" name="invoice_no" placeholder="Search Invoice #" class="form-control w-25 d-inline" value="{{ request('invoice_no') }}">
        <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Grand Total</th>
                <th>Cash Received</th>
                <th>Change</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_no }}</td>
                    <td>{{ $invoice->created_at->format('d-m-Y H:i') }}</td>
                    <td>{{ number_format($invoice->grand_total, 2) }}</td>
                    <td>{{ number_format($invoice->cash_received, 2) }}</td>
                    <td>{{ number_format($invoice->change_return, 2) }}</td>
                    <td>
                        <a href="{{ route('invoices.show', $invoice->invoice_no) }}" class="btn btn-sm btn-info">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">No invoices found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $invoices->links() }}
</div>
@endsection