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
	<div class="col-sm-5 col d-flex justify-content-end align-items-center">
		<a href="{{route('products.create')}}" class="btn btn-success mt-2 mr-3">Add Product</a>
		<button class="btn btn-primary mt-2" data-toggle="modal" data-target="#importGenericModal"><i class="fas fa-download"></i> Import from Generic Masterlist</button>
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
					<a href="javascript:void(0)" id="addStockBtn" class="btn btn-sm btn-primary ml-auto mr-2 btn-quick-stock"><i class="fas fa-plus"></i> Add
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

	<!-- Product Details Modal -->
	<div class="modal fade" id="productDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-xl" role="document">
			<div class="modal-content" id="productDetailsContent">
				<div class="modal-body text-center p-5">
					<div class="spinner-border text-primary" role="status"></div>
					<div class="mt-2">Loading product details...</div>
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

	<!-- Import Generic Modal -->
	<div class="modal fade" id="importGenericModal" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-md" role="document">
			<div class="modal-content">
				<div class="modal-header bg-primary text-white">
					<h5 class="modal-title">Import Generic Product</h5>
					<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body p-4">
					<p class="text-muted">Search the global masterlist to import a securely pre-configured generic product into your pharmacy.</p>
					<div class="form-group">
						<label>Select Generic Product(s)</label>
						<select id="generic_product_select" class="form-control" style="width: 100%;" multiple="multiple"></select>
					</div>
					<button type="button" id="btn-confirm-import-generic" class="btn btn-success btn-block mt-4"><i class="fas fa-check"></i> Import Selected Product(s)</button>
				</div>
			</div>
		</div>
	</div>
@endsection

@push('page-js')
	<script>
		$(document).ready(function () {
			var table = $('#product-table').DataTable({
				processing: false, // Turned off native flashing loader to use robust custom overlay
				serverSide: true,
				ajax: "{{route('products.index')}}",
				columns: [
					{ data: 'product_name', name: 'product_name' },
					{ data: 'strength', name: 'strength' },
					{ data: 'type', name: 'type' },
					{ data: 'company', name: 'company' },
					{ data: 'farmula', name: 'farmula' },
					{ data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-nowrap', width: '1%' },
				]
			});

			// Create and inject a perfect locking overlay over the table wrapper
			$('#product-table').wrap('<div class="position-relative" id="custom-dt-wrapper"></div>');
			$('#custom-dt-wrapper').append(
				'<div id="table-custom-loader" style="display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.85); z-index:50; display:flex; flex-direction:column; justify-content:center; align-items:center; min-height: 200px;">' +
					'<i class="fas fa-spinner fa-spin fa-3x" style="color: #007bff;"></i>' +
					'<span class="mt-2" style="font-weight: bold; color: #007bff;">Searching...</span>' +
				'</div>'
			);

			// Lock the UI instantly BEFORE network request deploys
			table.on('preXhr.dt', function ( e, settings, data ) {
				$('#table-custom-loader').css('display', 'flex').show();
			});

			// Unlock the UI instantly AFTER network payload arrives and formats
			table.on('draw.dt', function () {
				$('#table-custom-loader').hide();
			});

			$('#generic_product_select').select2({
				placeholder: 'Search for medicine...',
				dropdownParent: $('#importGenericModal'),
				ajax: {
					url: '{{ route('generic_products.autocomplete') }}',
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							q: params.term,
							page: params.page || 1
						};
					},
					processResults: function (data) {
						return data;
					},
					cache: true
				}
			});

			$('#btn-confirm-import-generic').on('click', function() {
				let productIds = $('#generic_product_select').val();
				if(!productIds || productIds.length === 0) {
					alert('Please search and select at least one generic product first.');
					return;
				}
				
				let btn = $(this);
				btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Importing...');

				$.ajax({
					url: "{{ route('generic_products.bulkImport') }}",
					type: 'POST',
					data: {
						_token: '{{ csrf_token() }}',
						product_ids: productIds
					},
					success: function(response) {
						btn.prop('disabled', false).html('<i class="fas fa-check"></i> Import Selected Product(s)');
						if(response.error) {
							alert(response.error);
						} else if(response.success) {
							$('#importGenericModal').modal('hide');
							$('#generic_product_select').val(null).trigger('change');
							
							$('#product-table').DataTable().ajax.reload(null, false);
							
							Snackbar.show({
								text: response.success,
								pos: 'top-right',
								actionTextColor: '#fff',
								backgroundColor: '#8dbf42'
							});
						}
					},
					error: function(err) {
						btn.prop('disabled', false).html('<i class="fas fa-check"></i> Import Selected Product(s)');
						alert('Error importing product. Please try again.');
					}
				});
			});

		});
		$(document).on('click', '.show-stock', function () {
			let productId = $(this).data('id');
			$('#addStockBtn').attr('data-id', productId);
			$('#stock-content').html('Loading...');
			$('#stockModal').modal('show');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/stock-summary",
				type: 'GET',
				success: function (res) {
					// Update modal title with product name
					$('#stockModal .modal-title').text('Stock Summary [' + res.product_name + ']');

					if (res.summary.available.length === 0 && res.summary.expired.length === 0) {
						$('#stock-content').html('<p class="text-muted text-center mt-3 mb-3">No stock available</p>');
						return;
					}

					let table = '';

					if (res.summary.available.length > 0) {
						table += '<h5 class="text-success mt-2 mb-2 font-weight-bold" style="font-size: 14px;">Available Stock</h5>';
						table += '<table class="table table-bordered table-sm">';
						table += '<thead class="bg-light"><tr><th>Category</th><th>Quantity</th></tr></thead><tbody>';
						res.summary.available.forEach(function (item) {
							table += '<tr><td>' + item.category + '</td><td>' + item.quantity + '</td></tr>';
						});
						table += '</tbody></table>';
					}

					if (res.summary.expired.length > 0) {
						table += '<h5 class="text-danger mt-3 mb-2 font-weight-bold" style="font-size: 14px;">Expired Stock</h5>';
						table += '<table class="table table-bordered table-sm">';
						table += '<thead class="bg-danger text-white"><tr><th>Category</th><th>Quantity</th></tr></thead><tbody>';
						res.summary.expired.forEach(function (item) {
							table += '<tr><td>' + item.category + '</td><td class="text-danger font-weight-bold">' + item.quantity + '</td></tr>';
						});
						table += '</tbody></table>';
					}

					$('#stock-content').html(table);
				},
				error: function (xhr) {
					$('#stock-content').html('<span class="text-danger">Failed to load stock summary. ' + xhr.status + '</span>');
				}
			});
		});

		$(document).on('click', '.btn-view-product-details', function () {
			let productId = $(this).data('id');
			$('#productDetailsModal').modal('show');
			$('#productDetailsContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading product details...</div></div>');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/details",
				type: 'GET',
				success: function (res) {
					$('#productDetailsContent').html(res);
				},
				error: function (xhr) {
					$('#productDetailsContent').html('<div class="modal-body text-danger p-5">Failed to load product details. ' + xhr.status + '</div>');
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
						table += '<thead class="bg-light"><tr><th class="portion-border-right">Date</th><th class="portion-border-right text-center">Batch no/Invoice no</th><th class="portion-border-right text-center">Remaining Base Qty</th><th class="portion-border-right text-center">Category</th><th class="portion-border-right">Category Sale Prices</th><th>Category Purchase Prices</th></tr></thead><tbody>';
						res.batches.forEach(function (batch) {
							let trClass = 'bg-light';
							if (batch.is_expired) {
								trClass = 'bg-danger text-white';
							} else if (batch.is_zero_qty) {
								trClass = 'bg-warning text-dark';
							}
							table += '<tr class="' + trClass + '">';
							table += '<td class="portion-border-right text-nowrap">' + batch.date + '</td>';
							table += '<td class="portion-border-right text-left">' + batch.batch_no + '</td>';
							table += '<td class="portion-border-right text-center">' + batch.remaining_stock + '</td>';
							table += '<td class="portion-border-right text-center">' + batch.category_name + '</td>';
							table += '<td class="portion-border-right">' + batch.prices + '</td>';
							table += '<td>' + batch.purchase_prices + '</td>';
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
			let productId = $(this).attr('data-id');
			if(!productId) {
				productId = $(this).data('id');
			}
			let btn = $(this);
			let originalContent = btn.html();
			
			$('#stockModal').modal('hide');
			btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

			$.ajax({
				url: "{{ url('admin/products') }}/" + productId + "/quick-stock",
				type: 'GET',
				success: function (res) {
					btn.prop('disabled', false).html(originalContent);
					setTimeout(function() {
						$('#quickStockContainer').html(res);
						$('#quickStockModal').modal('show');
					}, 400);
				},
				error: function (xhr) {
					btn.prop('disabled', false).html(originalContent);
					alert('Failed to load Quick Add Stock. Status: ' + xhr.status);
				}
			});
		});
	</script>
@endpush