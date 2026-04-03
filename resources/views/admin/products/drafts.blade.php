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
	<h3 class="page-title">Draft Products</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Draft Products</li>
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
        @if(session('auto_open_wizard'))
            let autoOpenId = {{ session('auto_open_wizard') }};
            $('#setupWizardModal').modal('show');
            $('#setupWizardContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading Setup Wizard...</div></div>');

            $.ajax({
                url: "{{ url('admin/products') }}/" + autoOpenId + "/setup-wizard",
                type: 'GET',
                success: function(res) {
                    $('#setupWizardContent').html(res);
                },
                error: function(xhr) {
                    $('#setupWizardContent').html('<div class="modal-body text-danger p-5">Failed to load setup wizard. ' + xhr.status + '</div>');
                }
            });
        @endif

        var table = $('#product-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{route('products.drafts')}}",
            columns: [
                {data: 'product_name', name: 'product_name'},
				{data: 'company', name: 'company.name'},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-nowrap', width: '1%'},
            ]
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