@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">{{ $title }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">{{ $title }}</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="#generate_report" data-toggle="modal" class="btn btn-success float-right mt-2">Filter Report</a>
</div>
@endpush

@section('content')

<!-- Summary Widgets -->
<div class="row">
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-info">
            <div class="card-body">
                <h4 class="text-white">Net Revenue</h4>
                <h3 class="text-white">{{number_format($totalRevenue, 2)}}</h3>
                <small class="text-white">After returns & discounts</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-warning">
            <div class="card-body">
                <h4 class="text-white">Net Est. Cost</h4>
                <h3 class="text-white">{{number_format($totalCost, 2)}}</h3>
                <small class="text-white">Avg cost * net quantity</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-danger">
            <div class="card-body">
                <h4 class="text-white">Return Impact</h4>
                <h3 class="text-white">{{number_format($totalReturnedRevenue, 2)}}</h3>
                <small class="text-white">Lost revenue from returns</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card {{ $netProfit >= 0 ? 'bg-success' : 'bg-danger' }}">
            <div class="card-body">
                <h4 class="text-white">Estimated Net Profit</h4>
                <h3 class="text-white">{{number_format($netProfit, 2)}}</h3>
                <small class="text-white">Revenue - Est. Cost</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
	<div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Profit Breakdown ({{ date('d M, Y', strtotime($fromDate)) }} to {{ date('d M, Y', strtotime($toDate)) }})</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="profit-report-table" class="datatable table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>Product</th>
                                <th>Net Qty</th>
                                <th>Ret Qty</th>
                                <th>Revenue</th>
                                <th>Est. Cost</th>
                                <th>Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables populated here -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right"><strong>Total:</strong></th>
                                <th>{{ $totalNetQty }}</th>
                                <th>{{ $totalReturnedQty }}</th>
                                <th>{{ number_format($sumNetRevenue, 2) }}</th>
                                <th>{{ number_format($sumNetCost, 2) }}</th>
                                <th>
                                    <b class="{{ $sumProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($sumProfit, 2) }}
                                    </b>
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
	</div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="generate_report" aria-hidden="true" role="dialog">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Filter Report</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="GET" action="{{route('reports.profit_loss')}}">
					<div class="row form-row">
						<div class="col-12">
							<div class="row">
								<div class="col-6">
									<div class="form-group">
										<label>From Date</label>
										<input type="date" name="from_date" class="form-control" value="{{ $fromDate ?? '' }}" required>
									</div>
								</div>
								<div class="col-6">
									<div class="form-group">
										<label>To Date</label>
										<input type="date" name="to_date" class="form-control" value="{{ $toDate ?? '' }}" required>
									</div>
								</div>
							</div>
						</div>
					</div>
					<button type="submit" class="btn btn-success btn-block mt-3">Filter</button>
				</form>
			</div>
		</div>
	</div>
</div>
<!-- /Filter Modal -->
@endsection

@push('page-js')
<script>
    $(document).ready(function(){
        if($.fn.DataTable.isDataTable('#profit-report-table')){
            $('#profit-report-table').DataTable().destroy();
        }
        $('#profit-report-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('reports.profit_loss') }}",
                data: function (d) {
                    d.from_date = "{{ $fromDate }}";
                    d.to_date = "{{ $toDate }}";
                }
            },
            columns: [
                {data: 'date', name: 'date'},
                {data: 'invoice_no', name: 'invoice_no'},
                {data: 'product_name', name: 'product_name'},
                {data: 'net_qty', name: 'net_qty'},
                {data: 'returned_qty', name: 'returned_qty'},
                {data: 'net_revenue', name: 'net_revenue'},
                {data: 'net_cost', name: 'net_cost'},
                {data: 'profit', name: 'profit'}
            ],
			dom: 'Blfrtip',
			buttons: [
				{
				extend: 'collection',
				text: 'Export Data',
				buttons: [
					{
						extend: 'pdf',
						exportOptions: { columns: "thead th:not(:last-child)" }
					},
					{
						extend: 'excel',
						exportOptions: { columns: "thead th:not(:last-child)" }
					},
					{
						extend: 'csv',
						exportOptions: { columns: "thead th:not(:last-child)" }
					},
					{
						extend: 'print',
						exportOptions: { columns: "thead th:not(:last-child)" }
					}
				]
				}
			]
		});
    });
</script>
@endpush
