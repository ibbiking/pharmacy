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
@endpush

@section('content')

<!-- Summary Widgets -->
<div class="row">
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-success">
            <div class="card-body">
                <h4 class="text-white">Gross Sales</h4>
                <h3 class="text-white">{{number_format($totalSales, 2)}}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-info">
            <div class="card-body">
                <h4 class="text-white">Total Discount</h4>
                <h3 class="text-white">{{number_format($totalDiscount, 2)}}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-danger">
            <div class="card-body">
                <h4 class="text-white">Total Returns</h4>
                <h3 class="text-white">{{number_format($totalReturnedAmount, 2)}}</h3>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 col-12">
        <div class="card bg-primary">
            <div class="card-body">
                <h4 class="text-white">Net Sales</h4>
                <h3 class="text-white">{{number_format(max(0, $totalSales - $totalReturnedAmount), 2)}}</h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
	<div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Invoices from {{ date('d M, Y', strtotime($fromDate)) }} to {{ date('d M, Y', strtotime($toDate)) }}</h4>
                <a href="#generate_report" data-toggle="modal" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm"><i class="fas fa-filter mr-1"></i> Filter Report</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="sales-report-table" class="datatable table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice No</th>
                                <th>Subtotal</th>
                                <th>Discount</th>
                                <th>Grand Total</th>
                                <th class="action-btn">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- DataTables populated here -->
                        </tbody>
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
				<form method="GET" action="{{route('reports.sales')}}">
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
        if($.fn.DataTable.isDataTable('#sales-report-table')){
            $('#sales-report-table').DataTable().destroy();
        }
        var table = $('#sales-report-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('reports.sales') }}",
                data: function (d) {
                    d.from_date = "{{ $fromDate }}";
                    d.to_date = "{{ $toDate }}";
                }
            },
            columns: [
                {data: 'date', name: 'created_at'},
                {data: 'invoice_no', name: 'invoice_no'},
                {data: 'subtotal', name: 'subtotal'},
                {data: 'discount', name: 'discount'},
                {data: 'grand_total', name: 'grand_total'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
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

    function printInvoiceReceipt(url) {
        window.open(url, 'PrintReceipt', 'width=800,height=600');
    }
</script>
@endpush
