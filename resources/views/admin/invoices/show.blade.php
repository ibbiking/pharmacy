@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12 col-auto">
    <h3 class="page-title">Invoice Details</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.sales') }}">Sales Report</a></li>
        <li class="breadcrumb-item active">Invoice #{{ $invoice->invoice_no }}</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <div class="card card-table">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Invoice #{{ $invoice->invoice_no }}</h4>
                <div>
                    <a href="{{ route('invoices.index') }}" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
                    <a href="javascript:void(0)" onclick="printReceiptPopup('{{ route('invoices.print', $invoice->invoice_no) }}')" class="btn btn-primary mr-2"><i class="fas fa-print"></i> Print Receipt</a>
                    @if($invoice->fully_returned)
                        <span class="badge badge-danger text-white py-2 px-3" style="font-size: 14px;">Fully Returned</span>
                    @else
                        <span class="badge badge-success text-white py-2 px-3 mr-2" style="font-size: 14px;">Paid</span>
                        <form action="{{ route('invoices.return', $invoice->invoice_no) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to return this entire invoice?');">
                            @csrf
                            <input type="hidden" name="reason" value="Full invoice return">
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="fas fa-undo"></i> Return Entire Invoice
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="card-body px-4 py-3">
                <div class="row border-bottom pb-4 mb-4">
                    <div class="col-md-6 col-sm-6">
                        <h5 class="font-weight-bold text-muted text-uppercase">Payment Info</h5>
                        <div class="mt-3">
                            <p class="mb-1"><strong>Invoice Date:</strong> {{ date('d M, Y h:i A', strtotime($invoice->created_at)) }}</p>
                            @if($invoice->discount > 0)
                            <p class="mb-1"><strong>Product Discounts:</strong> <span class="text-danger">-{{ number_format($invoice->discount, 2) }} RS</span></p>
                            @endif
                            @if($invoice->invoice_discount_amount > 0)
                            <p class="mb-1"><strong>Global Discount:</strong> <span class="text-danger">-{{ number_format($invoice->invoice_discount_amount, 2) }} RS</span></p>
                            @endif
                            <p class="mb-1"><strong>Cash Received:</strong> {{ number_format($invoice->cash_received, 2) }} RS</p>
                            <p class="mb-0"><strong>Change Returned:</strong> {{ number_format($invoice->change_return, 2) }} RS</p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-center table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Category</th>
                                <th class="text-right">Price</th>
                                <th class="text-center">Net Qty</th>
                                <th class="text-right">Unit Discount</th>
                                <th class="text-right">Row Total</th>
                                <th class="text-center">Return Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                                @php
                                    $hasReturns = $item->qty > $item->net_qty;
                                @endphp
                                <tr class="{{ $item->net_qty == 0 ? 'table-danger' : '' }}">
                                    <td>
                                        <h2 class="table-avatar">
                                            <a href="javascript:void(0);">
                                                {{ $item->product->product_name ?? $item->name }} 
                                                @if($item->product && $item->product->strength)
                                                    <span>({{ $item->product->strength->name ?? '' }})</span>
                                                @endif
                                            </a>
                                        </h2>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-info text-white">{{ $item->category->name ?? 'N/A' }}</span>
                                    </td>
                                    <td class="text-right">{{ number_format($item->price, 2) }}</td>
                                    <td class="text-center font-weight-bold">
                                        {{ $item->net_qty }}
                                        @if($hasReturns)
                                            <br><small class="text-danger" style="font-size: 11px;">(Returned: {{ $item->qty - $item->net_qty }})</small>
                                        @endif
                                    </td>
                                    <td class="text-right text-muted">
                                        @if($item->discount_amount > 0)
                                            <span class="text-danger">-{{ number_format($item->discount_amount, 2) }}</span><br>
                                            <small>({{ $item->discount_type == 'percent' ? $item->discount_value . '%' : floatval($item->discount_value) . ' RS' }})</small>
                                        @else
                                            0.00
                                        @endif
                                    </td>
                                    <td class="text-right font-weight-bold">{{ number_format($item->row_total, 2) }}</td>
                                    <td class="text-center">
                                        @if($item->net_qty > 0)
                                            <form action="{{ route('invoices.return-product', ['invoice_no' => $invoice->invoice_no, 'item_id' => $item->id]) }}" method="POST" class="d-flex align-items-center justify-content-center">
                                                @csrf
                                                <input type="number" name="return_qty" max="{{ $item->net_qty }}" min="1" class="form-control form-control-sm mr-2" style="width: 70px;" value="1" required>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Process return for this item?');">Refund</button>
                                            </form>
                                        @else
                                            <span class="badge badge-danger text-white">Refunded</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-sm-7 col-md-8">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <div class="bg-light p-3 rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal:</span>
                                <strong>{{ number_format($invoice->subtotal, 2) }}</strong>
                            </div>
                            @if($invoice->discount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Product Discounts:</span>
                                <strong class="text-danger">-{{ number_format($invoice->discount, 2) }}</strong>
                            </div>
                            @endif
                            @if($invoice->invoice_discount_amount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Global Discount:</span>
                                <strong class="text-danger">-{{ number_format($invoice->invoice_discount_amount, 2) }}</strong>
                            </div>
                            @endif
                            <hr class="mt-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold ml-1" style="font-size: 18px;">Grand Total:</span>
                                <strong class="text-success" style="font-size: 20px;">{{ number_format($invoice->grand_total, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-md-12">
                        <h5 class="font-weight-bold text-muted text-uppercase mb-3">Return History Summary</h5>
                        @if($invoice->returnHistories->isEmpty())
                            <p class="text-muted">No returns processed for this invoice.</p>
                        @else
                            <ul class="list-group">
                                @foreach($invoice->returnHistories()->orderBy('created_at', 'desc')->get() as $history)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong class="text-danger">{{ $history->action }}</strong>: {{ $history->description }} 
                                            @if($history->return_no)
                                                <a href="{{ route('returns.show', $history->return_no) }}" class="badge badge-info ml-2">View</a>
                                            @endif
                                        </div>
                                        <div>
                                            <small class="text-muted">{{ $history->created_at->format('d M, Y h:i A') }}</small>
                                        </div>
                                    </div>
                                    @if($history->return_no)
                                    @php
                                        $retItems = \App\Models\InvoiceItemReturn::where('return_no', $history->return_no)->with('product.strength')->get();
                                    @endphp
                                    @if($retItems->count() > 0)
                                    <div class="mt-3 table-responsive">
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 13px;">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Returned Product</th>
                                                    <th class="text-center">Ret Qty</th>
                                                    <th class="text-right">Unit Disc Deducted</th>
                                                    <th class="text-right">Global Disc Clawback</th>
                                                    <th>Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($retItems as $ritem)
                                                <tr>
                                                    <td>{{ $ritem->product->product_name ?? 'N/A' }} {{ $ritem->product && $ritem->product->strength ? '('.$ritem->product->strength->name.')' : '' }}</td>
                                                    <td class="text-center text-danger font-weight-bold">{{ $ritem->qty_returned }}</td>
                                                    <td class="text-right text-warning font-weight-bold">{{ number_format($ritem->unit_discount_deducted, 2) }}</td>
                                                    <td class="text-right text-warning font-weight-bold">{{ number_format($ritem->global_discount_clawback, 2) }}</td>
                                                    <td class="text-muted">{{ $ritem->reason ?: '-' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @endif
                                    @endif
                                </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection

@push('page-js')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        @if(session('print_return_payload'))
            const returnData = @json(session('print_return_payload'));
            const invoiceNo = "{{ $invoice->invoice_no }}";

            const payload = {
                invoice_no: invoiceNo,
                returnedItems: returnData
            };

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('invoices.print-return-receipt') }}";
            form.target = 'print_window';

            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            const payloadInput = document.createElement('input');
            payloadInput.type = 'hidden';
            payloadInput.name = 'payload';
            payloadInput.value = JSON.stringify(payload);
            form.appendChild(payloadInput);

            document.body.appendChild(form);

            const printWindow = window.open('', 'print_window', 'width=400,height=600');
            if (printWindow) {
                form.submit();
            } else {
                alert('Popup blocked. Please allow popups to print the return receipt.');
            }

            document.body.removeChild(form);
        @endif
    });

    function printReceiptPopup(url) {
        window.open(url, 'PrintReceipt', 'width=800,height=600');
    }
</script>
@endpush