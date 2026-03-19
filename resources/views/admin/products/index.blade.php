@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')
<style>
.modal.right .modal-dialog-slideout {
    position: fixed;
    margin: auto;
    width: 90%;
    max-width: 900px;
    height: 100%;
    transform: translate3d(100%, 0, 0);
    transition: transform 0.3s ease-out;
}
.modal.right.show .modal-dialog-slideout {
    transform: translate3d(0, 0, 0);
    right: 0;
}
.modal.right .modal-content {
    height: 100%;
    overflow-y: auto;
    border-radius: 0;
    border: none;
}
</style>
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
                                <th>Strength</th>
								<th>Product Type</th>
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
				<a href="#" id="addStockBtn" class="btn btn-sm btn-primary ml-auto mr-2"><i class="fas fa-plus"></i> Add Stock</a>
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

<!-- Setup Wizard Modal Slideout -->
<div class="modal fade right" id="setupWizardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-slideout" role="document">
        <div class="modal-content" id="setupWizardContent">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2">Loading Setup Wizard...</div>
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
                {data: 'strength', name: 'strength'},
                {data: 'type', name: 'type'},
				{data: 'company', company: 'company'},
				{data: 'farmula', farmula: 'farmula'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
        
    });
	$(document).on('click', '.show-stock', function() {
    let productId = $(this).data('id');
    $('#addStockBtn').attr('href', '{{ route('purchases.create') }}?product_id=' + productId);
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
	$(document).on('click', '.btn-setup-wizard', function() {
        let productId = $(this).data('id');
        $('#setupWizardModal').modal('show');
        $('#setupWizardContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading Setup Wizard...</div></div>');

        $.ajax({
            url: "{{ url('admin/products') }}/" + productId + "/setup-wizard",
            type: 'GET',
            success: function(res) {
                $('#setupWizardContent').html(res);
            },
            error: function(xhr) {
                $('#setupWizardContent').html('<div class="modal-body text-danger p-5">Failed to load setup wizard. ' + xhr.status + '</div>');
            }
        });
    });
</script>
@endpush