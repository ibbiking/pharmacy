@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')
    
@endpush

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Purchase</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Purchase</li>
	</ul>
</div>
<div class="col-sm-5 col d-flex justify-content-end align-items-center">
	<a href="{{route('purchases.create-multiple')}}" class="btn btn-primary mt-2 mr-2 rounded-pill px-4 shadow-sm"><i class="fas fa-layer-group mr-1"></i> Add Multiple</a>
	<a href="{{route('purchases.create')}}" class="btn btn-success mt-2 rounded-pill px-4 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Purchase</a>
</div>
@endpush
<!-- Visit codeastro.com for more projects -->
@section('content')
<div class="row">
	<div class="col-md-12">
	
		<!-- Recent Orders -->
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table id="purchase-table" class="datatable table table-hover table-center mb-0">
						<thead>
							<tr>
								<th>Medicine Name</th>
								<th>Category</th>
								<th>Supplier</th>
								<th>Purchase Cost</th>
								<th>Paid Cost</th>
								<th>Quantity</th>
								<th>Expire Date</th>
								<th>Invoice No.</th>
								<th class="action-btn">Action</th>
							</tr>
						</thead>
						<tbody>
														
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<!-- /Recent Orders -->
		
	</div>
</div>
@endsection	

@push('page-js')
<script>
    $(document).ready(function() {
        var table = $('#purchase-table').DataTable({
            processing: true,
            serverSide: true,
            order: [],
            ajax: "{{route('purchases.index')}}",
            columns: [
                {data: 'product', name: 'product'},
                {data: 'category', name: 'category'},
                {data: 'supplier', name: 'supplier'},
                {data: 'unit_cost_price', name: 'unit_cost_price'},
                {data: 'paid_unit_cost_price', name: 'paid_unit_cost_price'},
                {data: 'quantity', name: 'quantity'},
				{data: 'expiry_date', name: 'expiry_date'},
				{data: 'invoice_no', name: 'invoice_no'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
        
    });
</script> 
@endpush