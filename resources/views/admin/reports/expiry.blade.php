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

<!-- Expiry Summary Widgets -->
<div class="row">
    <div class="col-xl-4 col-sm-6 col-12">
        <div class="card bg-danger">
            <div class="card-body">
                <h4 class="text-white">Critical Expiries (< 30 Days)</h4>
                <h3 class="text-white">{{ $totalCount }} Batches</h3>
                <small class="text-white">Within {{ $duration }} months</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
	<div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Products Expiring within Next {{ $duration }} Months</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="expiry-report-table" class="datatable table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th>Category</th>
                                <th>Batch No</th>
                                <th>Remaining Qty</th>
                                <th>Purchase Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
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
				<h5 class="modal-title">Filter Expiry Report</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<form method="GET" action="{{route('reports.expiry')}}">
					<div class="row form-row">
						<div class="col-12">
                            <div class="form-group">
                                <label>Lookup Duration (Months from today)</label>
                                <input type="number" name="duration" class="form-control" value="{{ $duration ?? 6 }}" min="1" max="120" required>
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
        if($.fn.DataTable.isDataTable('#expiry-report-table')){
            $('#expiry-report-table').DataTable().destroy();
        }
        $('#expiry-report-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{ route('reports.expiry') }}",
                data: function (d) {
                    d.duration = "{{ $duration }}";
                }
            },
            columns: [
                {data: 'product', name: 'product.product_name'},
                {data: 'category', name: 'category.name'},
                {data: 'batch_no', name: 'batch_no'},
                {data: 'quantity', name: 'quantity'},
                {data: 'purchase_date', name: 'created_at'},
                {data: 'expiry_date', name: 'expiry_date'},
                {data: 'status', name: 'status', orderable: false, searchable: false},
            ],
			dom: 'Blfrtip',
			buttons: [
		});
    });
</script>
@endpush
