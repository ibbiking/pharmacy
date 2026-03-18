@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')
    <link rel="stylesheet" href="{{asset('assets/plugins/chart.js/Chart.min.css')}}">
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Welcome {{auth()->user()->name}}!</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item active">Dashboard</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card">
            <div class="card-body">
                <div class="dash-widget-header">
                    <span class="dash-widget-icon text-success border-success">
                        <i class="fe fe-money"></i>
                    </span>
                    <div class="dash-count">
                        <h3>{{AppSettings::get('app_currency', '$')}} {{$today_sales}}</h3>
                    </div>
                </div>
                <div class="dash-widget-info">
                    <h6 class="text-muted">Today's Sales</h6>
                    <!-- <div class="progress progress-sm">
                        <div class="progress-bar bg-success w-50"></div>
                    </div> -->
                </div>
            </div>
        </div>
    </div><!-- Visit codeastro.com for more projects -->
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card">
            <div class="card-body">
                <div class="dash-widget-header">
                    <span class="dash-widget-icon text-info">
                        <i class="fa fa-th-large"></i>
                    </span>
                    <div class="dash-count">
                        <h3>{{$total_categories}}</h3>
                    </div>
                </div>
                <div class="dash-widget-info">
                    
                    <h6 class="text-muted">Available Categories</h6>
                    <!-- <div class="progress progress-sm">
                        <div class="progress-bar bg-info w-50"></div>
                    </div> -->
                </div><!-- Visit codeastro.com for more projects -->
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card">
            <div class="card-body">
                <div class="dash-widget-header">
                    <span class="dash-widget-icon text-danger border-danger">
                        <i class="fe fe-folder"></i>
                    </span>
                    <div class="dash-count">
                        <h3>{{$total_expired_products}}</h3>
                    </div>
                </div>
                <div class="dash-widget-info">
                    
                    <h6 class="text-muted">Expired Medicines</h6>
                    <!-- <div class="progress progress-sm">
                        <div class="progress-bar bg-danger w-50"></div>
                    </div> -->
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card">
            <div class="card-body">
                <div class="dash-widget-header">
                    <span class="dash-widget-icon text-warning border-warning">
                        <i class="fe fe-users"></i>
                    </span>
                    <div class="dash-count">
                        <h3>{{\DB::table('users')->count()}}</h3>
                    </div>
                </div>
                <div class="dash-widget-info">
                    
                    <h6 class="text-muted">System Users</h6>
                    <!-- <div class="progress progress-sm">
                        <div class="progress-bar bg-warning w-50"></div>
                    </div> -->
                </div>
            </div>
        </div>
    </div>
</div><!-- Visit codeastro.com for more projects -->
<div class="row">
    <div class="col-md-12 col-lg-7">
        <div class="card card-table p-3">
            <div class="card-header">
                <h4 class="card-title ">Recent Sales List</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="sales-table" class="datatable table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Invoice No.</th>
                                <th>Total Items</th>
                                <th>Grand Total</th>
                                <th>Date Generated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latest_sales as $invoice)
                                <tr>
                                    <td>{{ $invoice->invoice_no }}</td>
                                    <td>{{ $invoice->items->count() }}</td>
                                    <td>{{ AppSettings::get('app_currency', '$') }} {{ number_format($invoice->grand_total, 2) }}</td>
                                    <td>{{ $invoice->created_at->format('d M, Y - h:i A') }}</td>
                                    <td><a href="{{ route('invoices.show', $invoice->invoice_no) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No recent sales found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-table p-3">
            <div class="card-header border-bottom">
                <h4 class="card-title">Recent Returns List</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Qty Returned</th>
                                <th>Reason</th>
                                <th>Date Returned</th>
                                <th>Linked Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latest_returns as $return)
                                <tr>
                                    <td>
                                        @if($return->invoiceItem && $return->invoiceItem->product)
                                            {{ $return->invoiceItem->product->product_name }}
                                        @else
                                            Unknown Product
                                        @endif
                                    </td>
                                    <td><span class="badge badge-danger">{{ $return->qty_returned }}</span></td>
                                    <td>{{ $return->reason ?? 'N/A' }}</td>
                                    <td>{{ $return->created_at->format('d M, Y') }}</td>
                                    <td>
                                        <!-- Check if invoice relation exists via nested invoiceItem -->
                                        @if($return->invoiceItem && $return->invoiceItem->invoice)
                                            <a href="{{ route('invoices.show', $return->invoiceItem->invoice->invoice_no) }}">
                                                {{ $return->invoiceItem->invoice->invoice_no }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No recent returns found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Expiring Medicines -->
        <div class="card card-table p-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title ">Medicines Expiring Soon (Next 6 Months)</h4>
                <a href="{{ route('reports.expiry') }}" class="btn btn-sm btn-outline-primary">View Full Report</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Batch No</th>
                                <th>Remaining Qty</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expiring_purchases as $purchase)
                                @php
                                    $expiryDate = \Carbon\Carbon::parse($purchase->expiry_date);
                                    $today = \Carbon\Carbon::now();
                                    
                                    $isPast = $expiryDate->isPast();
                                    $daysRemaining = $today->diffInDays($expiryDate, false);
                                    
                                    $rowClass = '';
                                    if ($isPast || $daysRemaining <= 30) {
                                        $rowClass = 'bg-danger text-white';
                                    } elseif ($daysRemaining <= 90) {
                                        $rowClass = 'bg-warning text-dark';
                                    }
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td>{{$purchase->product->product_name ?? 'Unknown'}}</td>
                                    <td>{{$purchase->batch_no ?? 'N/A'}}</td>
                                    <td>{{$purchase->quantity}}</td>
                                    <td><b>{{date_format(date_create($purchase->expiry_date),"d M, Y")}}</b></td>
                                    <td>
                                        @if($isPast)
                                            Expired
                                        @else
                                            Expire in {{ (int)$daysRemaining }} days
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No medicines expiring soon!</td>
                                </tr>
                            @endforelse
                                                                                      
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div><!-- Visit codeastro.com for more projects -->

    <div class="col-md-12 col-lg-5">
        
        <!-- Bar Chart -->
        <div class="card card-chart shadow-sm mb-4">
            <div class="card-header border-bottom">
                <h4 class="card-title text-center">7 Days Revenue</h4>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height: 300px;">
                    {!! $barChart->render() !!}
                </div>
            </div>
        </div>
        <!-- /Bar Chart -->

        <!-- Pie Chart -->
        <div class="card card-chart shadow-sm">
            <div class="card-header border-bottom">
                <h4 class="card-title text-center">System Overview</h4>
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height: 300px;">
                    {!! $pieChart->render() !!}
                </div>
                <!-- Navigation Links for Report -->
                <div class="mt-4 text-center">
                    <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-outline-danger">View Purchases</a>
                    <a href="{{ route('suppliers.index') }}" class="btn btn-sm btn-outline-info">View Suppliers</a>
                    <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-success">View Sales</a>
                </div>
            </div>
        </div>
        <!-- /Pie Chart -->
        
    </div>	
    
    
</div>

@endsection

@push('page-js')
<script>
    $(document).ready(function() {
        // DataTables on Dashboard usually redundant for 'recent 5'
    });
</script> 
<script src="{{asset('assets/plugins/chart.js/Chart.bundle.min.js')}}"></script>
@endpush