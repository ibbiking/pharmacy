@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12 col-auto">
    <h3 class="page-title">Return Details</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{ route('reports.returns') }}">Return Report</a></li>
        <li class="breadcrumb-item active">Return #{{ $return_no }}</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card card-table">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Return #{{ $return_no }}</h4>
                <div>
                    <a href="{{ route('reports.returns') }}" class="btn btn-secondary mr-2"><i class="fas fa-arrow-left"></i> Back to Returns</a>
                    <a href="javascript:void(0)" onclick="printReceiptPopup('{{ route('returns.print', $return_no) }}')" class="btn btn-primary mr-2"><i class="fas fa-print"></i> Print Receipt</a>
                </div>
            </div>
            <div class="card-body px-4 py-3">
                <div class="row border-bottom pb-4 mb-4">
                    <div class="col-md-6 col-sm-6">
                        <h5 class="font-weight-bold text-muted text-uppercase">Return Info</h5>
                        <div class="mt-3">
                            <p class="mb-1"><strong>Parent Invoice No:</strong> <a href="{{ route('invoices.show', $invoice->invoice_no) }}">{{ $invoice->invoice_no }}</a></p>
                            <p class="mb-1"><strong>Invoice Date:</strong> {{ date('d M, Y h:i A', strtotime($invoice->created_at)) }}</p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover table-center table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Product Type</th>
                                <th class="text-right">Price</th>
                                <th class="text-center">Return Qty</th>
                                <th class="text-right">Gross Total</th>
                                <th class="text-right">Net Return Val</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>
                                        <h2 class="table-avatar">
                                            <a href="javascript:void(0);">
                                                {{ $item['name'] }}
                                                @if($item['strength'])
                                                    <span>({{ $item['strength'] }})</span>
                                                @endif
                                            </a>
                                            @if($item['product_type'])
                                                <div class="mt-1">
                                                    <small class="text-info font-weight-bold" style="text-transform: uppercase;">{{ $item['product_type'] }}</small>
                                                </div>
                                            @endif
                                        </h2>
                                    </td>
                                    <td class="text-center">
                                        <i class="fas fa-undo text-danger" title="Returned Item"></i>
                                    </td>
                                    <td class="text-right">{{ number_format($item['price'], 2) }}</td>
                                    <td class="text-center font-weight-bold text-danger">
                                        {{ $item['qty'] }}
                                    </td>
                                    <td class="text-right font-weight-bold">{{ number_format($item['gross_total'], 2) }}</td>
                                    <td class="text-right text-success font-weight-bold">{{ number_format($item['total'], 2) }}</td>
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
                                <strong>{{ number_format($grossSubtotal, 2) }}</strong>
                            </div>
                            @if($totalUnitDiscount > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Product Discounts Lost:</span>
                                <strong class="text-danger">-{{ number_format($totalUnitDiscount, 2) }}</strong>
                            </div>
                            @endif
                            @if($totalClawback > 0)
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Global Discount Canceled:</span>
                                <strong class="text-danger">-{{ number_format($totalClawback, 2) }}</strong>
                            </div>
                            @endif
                            <hr class="mt-2 mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="font-weight-bold ml-1" style="font-size: 18px;">Total Cash Refund:</span>
                                <strong class="text-success" style="font-size: 20px;">{{ number_format($totalReturn - $totalClawback, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-md-12">
                        <h5 class="font-weight-bold text-muted text-uppercase mb-3">Parent Invoice History</h5>
                        <ul class="list-group">
                            @foreach($invoice->histories()->orderBy('created_at', 'desc')->get() as $history)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-primary">{{ $history->action }}</strong>: {{ $history->description }}
                                    </div>
                                    <div>
                                        <small class="text-muted">{{ $history->created_at->format('d M, Y h:i A') }}</small>
                                    </div>
                                </div>
                                @if($invoice->items)
                                <div class="mt-3 table-responsive">
                                    <table class="table table-sm table-bordered mb-0" style="font-size: 13px;">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Product Name</th>
                                                <th class="text-center">Orig Qty</th>
                                                <th class="text-center">Ret Qty (Up to Event)</th>
                                                <th class="text-center">Remaining Net Qty</th>
                                                <th class="text-right">Price</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($invoice->items as $iItem)
                                            @php
                                                // calculate returns up to this specific history event time (+2s buffer for same-request inserts)
                                                $returnedQty = $iItem->returns ? $iItem->returns->filter(function($r) use ($history) {
                                                    return $r->created_at->timestamp <= $history->created_at->timestamp + 2;
                                                })->sum('qty_returned') : 0;
                                                $netQty = $iItem->qty - $returnedQty;
                                            @endphp
                                            <tr>
                                                <td>{{ $iItem->product->product_name ?? $iItem->name }} {{ $iItem->product && $iItem->product->strength ? '('.$iItem->product->strength->name.')' : '' }}</td>
                                                <td class="text-center">{{ $iItem->qty }}</td>
                                                <td class="text-center text-danger font-weight-bold">{{ $returnedQty }}</td>
                                                <td class="text-center text-success font-weight-bold">{{ $netQty }}</td>
                                                <td class="text-right">{{ number_format($iItem->price, 2) }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    function printReceiptPopup(url) {
        window.open(url, 'PrintReceipt', 'width=800,height=600');
    }
</script>
@endpush
