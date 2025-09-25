@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')

@endpush

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Products</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Products</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('products.create')}}" class="btn btn-success float-right mt-2">Add Product</a>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">

		<!-- Products -->
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table id="product-table" class="datatable table table-hover table-center mb-0">
						<thead>
							<tr>
								<th>Product Name</th>
								<th>Company</th>
								<th>Farmula</th>
								<th class="action-btn">Action</th>
							</tr>
						</thead>
						<tbody>


						</tbody>
					</table>
				</div>
			</div>
		</div>
		<!-- /Products -->
		<!-- Visit codeastro.com for more projects -->
	</div>
</div>
<div class="modal fade" id="stockModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Stock Summary</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="stock-content">Loading...</div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script>
	$(document).ready(function() {
        var table = $('#product-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{route('products.index')}}",
            columns: [
                {data: 'product_name', name: 'product_name'},
				{data: 'company', company: 'company'},
				{data: 'farmula', farmula: 'farmula'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
        
    });
	$(document).on('click', '.show-stock', function() {
    let productId = $(this).data('id');
    $('#stock-content').html('Loading...');
    $('#stockModal').modal('show');

    $.ajax({
    url: "{{ url('admin/products') }}/" + productId + "/stock-summary",
    type: 'GET',
    success: function(res) {
    // Update modal title with product name
    $('#stockModal .modal-title').text('Stock Summary [' + res.product_name + ']');

    if (res.summary.length === 0) {
        $('#stock-content').html('<p>No stock available</p>');
        return;
    }

    let table = '<table class="table table-bordered">';
    table += '<thead><tr><th>Category</th><th>Quantity</th></tr></thead><tbody>';

    res.summary.forEach(function(item) {
        table += '<tr><td>' + item.category + '</td><td>' + item.quantity + '</td></tr>';
    });

    table += '</tbody></table>';

    $('#stock-content').html(table);
},
    error: function(xhr) {
        $('#stock-content').html('<span class="text-danger">Failed to load stock summary. ' + xhr.status + '</span>');
    }
});
});
</script>
@endpush