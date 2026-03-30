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

		/* Price Summary Table Custom Styles */
		.table-price-summary th,
		.table-price-summary td {
			vertical-align: middle;
		}

		.portion-border-right {
			border-right: 3px solid #adb5bd !important;
		}

		.th-active {
			background-color: #fff3cd !important;
			color: #856404 !important;
		}

		.td-active {
			background-color: #fffaeb !important;
		}

		.th-static {
			background-color: #d1ecf1 !important;
			color: #0c5460 !important;
		}

		.td-static {
			background-color: #f2fbfc !important;
		}

		.th-stock {
			background-color: #d4edda !important;
			color: #155724 !important;
		}

		.td-stock {
			background-color: #f0fdf3 !important;
		}

		.table-price-summary tbody tr:hover td {
			filter: brightness(0.92);
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
					<a href="#" id="addStockBtn" class="btn btn-sm btn-primary ml-auto mr-2"><i class="fas fa-plus"></i> Add
						Stock</a>
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

	<div class="modal fade" id="priceSummaryModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Price Summary</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div id="price-summary-content">Loading...</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Container for Quick Stock Modal loaded via AJAX -->
	<div id="quickStockContainer"></div>
@endsection

@push('page-js')
	<script>
		$(document).ready(function () {
			var table = $('#product-table').DataTable({
				processing: true,
				serverSide: true,
				ajax: "{{route('products.index')}}",
				columns: [
					{ data: 'product_name', name: 'product_name' },
					{ data: 'strength', name: 'strength' },
					{ data: 'type', name: 'type' },
					{ data: 'company', name: 'company' },
					{ data: 'farmula', name: 'farmula' },
					{ data: 'action', name: 'action', orderable: false, searchable: false },
				]
			});

		});
		$(document).on('click', '.show-stock', function () {
			let productId = $(this).data('id');
			$('#addStockBtn').attr('href', '{{ route('purchases.create') }}?product_id=' + productId);
			$('#stock-content').html('Loading...');
			$('#stockModal').modal('show');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/stock-summary",
				type: 'GET',
				success: function (res) {
					// Update modal title with product name
					$('#stockModal .modal-title').text('Stock Summary [' + res.product_name + ']');

					if (res.summary.length === 0) {
						$('#stock-content').html('<p>No stock available</p>');
						return;
					}

					let table = '<table class="table table-bordered">';
					table += '<thead><tr><th>Category</th><th>Quantity</th></tr></thead><tbody>';

					res.summary.forEach(function (item) {
						table += '<tr><td>' + item.category + '</td><td>' + item.quantity + '</td></tr>';
					});

					table += '</tbody></table>';

					$('#stock-content').html(table);
				},
				error: function (xhr) {
					$('#stock-content').html('<span class="text-danger">Failed to load stock summary. ' + xhr.status + '</span>');
				}
			});
		});
		$(document).on('click', '.btn-setup-wizard', function () {
			let productId = $(this).data('id');
			$('#setupWizardModal').modal('show');
			$('#setupWizardContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading Setup Wizard...</div></div>');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/setup-wizard",
				type: 'GET',
				success: function (res) {
					$('#setupWizardContent').html(res);
				},
				error: function (xhr) {
					$('#setupWizardContent').html('<div class="modal-body text-danger p-5">Failed to load setup wizard. ' + xhr.status + '</div>');
				}
			});
		});

		$(document).on('click', '.show-price-summary', function () {
			let productId = $(this).data('id');
			$('#price-summary-content').html('Loading...');
			$('#priceSummaryModal').modal('show');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/price-summary",
				type: 'GET',
				success: function (res) {
					$('#priceSummaryModal .modal-title').text('Price Summary [' + res.product_name + ']');

					let table = '<div class="alert alert-info"><strong>Active Sale Preference:</strong> ' + res.active_preference + '<br><strong>Tax Included:</strong> ' + res.tax_included + '</div>';

					if (res.summary.length === 0) {
						$('#price-summary-content').html(table + '<p>No pricing parameters found.</p>');
						return;
					}

					table += '<div class="table-responsive"><table class="table table-bordered table-sm mb-4 table-price-summary">';
					table += '<thead><tr><th rowspan="2" class="align-middle text-center portion-border-right bg-light">Category</th><th colspan="4" class="text-center th-active portion-border-right">Active Price</th><th colspan="2" class="text-center th-static portion-border-right">Static Price</th><th colspan="4" class="text-center th-stock">Previous Stock / Purchase Price</th></tr>';
					table += '<tr><th class="text-center th-active">Purchase</th><th class="text-center th-active">Pur. Tax</th><th class="text-center th-active">Sale</th><th class="text-center th-active portion-border-right">Sale Tax</th><th class="text-center th-static">Purchase</th><th class="text-center th-static portion-border-right">Sale</th><th class="text-center th-stock">Purchase</th><th class="text-center th-stock">Pur. Tax</th><th class="text-center th-stock">Sale</th><th class="text-center th-stock">Sale Tax</th></tr></thead><tbody>';

					res.summary.forEach(function (item) {
						table += '<tr><td class="portion-border-right bg-light text-center"><strong>' + item.category + '</strong></td>';
						table += '<td class="td-active text-center">' + item.active_purchase_price + '</td>';
						table += '<td class="td-active text-center">' + item.active_purchase_tax + '</td>';
						table += '<td class="td-active text-center font-weight-bold">' + item.active_sale_price + '</td>';
						table += '<td class="td-active text-center portion-border-right">' + item.active_sale_tax + '</td>';
						table += '<td class="td-static text-center">' + item.static_purchase + '</td>';
						table += '<td class="td-static text-center portion-border-right">' + item.static_sale + '</td>';
						table += '<td class="td-stock text-center">' + item.stock_purchase + '</td>';
						table += '<td class="td-stock text-center">' + item.stock_purchase_tax + '</td>';
						table += '<td class="td-stock text-center">' + item.stock_sale + '</td>';
						table += '<td class="td-stock text-center">' + item.stock_sale_tax + '</td></tr>';
					});

					table += '</tbody></table></div>';

					if (res.batches && res.batches.length > 0) {
						table += '<h5 class="mt-3">Batches (Stock Wise Prices)</h5>';
						table += '<div class="table-responsive"><table class="table table-bordered table-sm">';
						table += '<thead class="bg-light"><tr><th class="portion-border-right">Date</th><th class="portion-border-right text-center">Invoice/Batch No</th><th class="portion-border-right text-center">Remaining Base Qty</th><th>Category Sale Prices</th></tr></thead><tbody>';
						res.batches.forEach(function (batch) {
							let trClass = 'bg-light';
							if (batch.is_expired) {
								trClass = 'bg-danger text-white';
							} else if (batch.is_zero_qty) {
								trClass = 'bg-warning text-dark';
							}
							table += '<tr class="' + trClass + '">';
							table += '<td class="portion-border-right text-nowrap">' + batch.date + '</td>';
							table += '<td class="portion-border-right text-center">' + batch.batch_no + '</td>';
							table += '<td class="portion-border-right text-center">' + batch.remaining_stock + '</td>';
							table += '<td>' + batch.prices + '</td>';
							table += '</tr>';
						});
						table += '</tbody></table></div>';
					}

					$('#price-summary-content').html(table);
				},
				error: function (xhr) {
					$('#price-summary-content').html('<span class="text-danger">Failed to load price summary. ' + xhr.status + '</span>');
				}
			});
		});

		// Quick Load Form Modal
		$(document).on('click', '.btn-quick-stock', function () {
			let productId = $(this).data('id');
			let btn = $(this);
			
			btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/quick-stock",
				type: 'GET',
				success: function (res) {
					btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
					$('#quickStockContainer').html(res);
					$('#quickStockModal').modal('show');
				},
				error: function (xhr) {
					btn.prop('disabled', false).html('<i class="fas fa-plus"></i>');
					alert('Failed to load Quick Add Stock. Status: ' + xhr.status);
				}
			});
		});
	</script>
@endpush