@extends('admin.layouts.app')

@push('page-css')
<link rel="stylesheet" href="{{asset('assets/css/bootstrap-datetimepicker.min.css')}}">
<style>
	.purchase-item-card {
		border: 1px solid #e3e8ee;
		border-radius: 8px;
		background: #f8fafc;
		transition: all 0.2s ease-in-out;
	}
	.purchase-item-card:hover {
		box-shadow: 0 4px 12px rgba(0,0,0,0.05);
	}
	.item-card-header {
		background: #edf2f7;
		border-bottom: 1px solid #e2e8f0;
		border-top-left-radius: 8px;
		border-top-right-radius: 8px;
		padding: 10px 18px;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
</style>
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Add Multiple Purchase</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('purchases.index')}}">Purchases</a></li>
		<li class="breadcrumb-item active">Add Multiple Purchase</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<form method="post" action="{{route('purchases.store-multiple')}}" enctype="multipart/form-data" autocomplete="off" id="multiplePurchaseForm">
			@csrf

			<!-- Top Shared Section: Supplier & Invoice No -->
			<div class="card mb-4 border-primary">
				<div class="card-header bg-primary text-white">
					<h5 class="card-title text-white mb-0"><i class="fas fa-file-invoice mr-2"></i> General Invoice Details</h5>
				</div>
				<div class="card-body">
					<div class="row">
						<div class="col-lg-6">
							<div class="form-group mb-0">
								<label class="font-weight-bold">Supplier <span class="text-danger">*</span></label>
								@php
									$oldSupplier = old('supplier');
								@endphp
								<select class="select2-tags-single form-control" name="supplier" id="main_supplier" data-placeholder="Select or Type New Supplier" required>
									<option value=""></option>
									@if($oldSupplier && !$suppliers->contains('id', $oldSupplier))
										<option value="{{ $oldSupplier }}" selected>{{ $oldSupplier }}</option>
									@endif
									@foreach ($suppliers as $supplier)
									<option value="{{$supplier->id}}" {{ $oldSupplier == $supplier->id ? 'selected' : '' }}>{{$supplier->name}}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-lg-6">
							<div class="form-group mb-0">
								<label class="font-weight-bold">Invoice No / Ref</label>
								<input class="form-control" type="text" name="invoice_no" placeholder="e.g. INV-2026-001" value="{{ old('invoice_no') }}">
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Container for Multiple Purchase Item Cards -->
			<div id="purchaseItemsContainer">
				<!-- Item cards will be rendered dynamically here -->
			</div>

			<!-- Form Action Buttons -->
			<div class="card mb-4">
				<div class="card-body d-flex justify-content-between align-items-center">
					<button type="button" class="btn btn-outline-primary btn-lg" id="btnAddPurchaseItem">
						<i class="fas fa-plus-circle mr-1"></i> Add Another Purchase Item
					</button>

					<div>
						<a href="{{route('purchases.index')}}" class="btn btn-secondary btn-lg mr-2">Cancel</a>
						<button type="submit" class="btn btn-success btn-lg px-4"><i class="fas fa-save mr-1"></i> Submit All Purchases</button>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>
@endsection

@push('page-js')
<script>
	var availableProducts = @json($products);
	var availableCategories = @json($categories);
	var availableTaxes = @json($taxes);
	var preselectedProductId = "{{ $preselectedProductId ?? '' }}";
	var itemCardCounter = 0;

	// Template renderer for a single Purchase Item Card
	function createPurchaseItemCardHtml(index, productPreselectId) {
		let optionsProducts = '<option value="">Select Medicine</option>';
		availableProducts.forEach(function(p) {
			let str = p.strength ? ' (' + p.strength.name + ')' : '';
			let sel = (productPreselectId && productPreselectId == p.id) ? 'selected' : '';
			optionsProducts += `<option value="${p.id}" ${sel}>${p.product_name}${str}</option>`;
		});

		let optionsPurchaseTaxes = '<option value="">Select Purchase Tax</option>';
		let optionsSaleTaxes = '<option value="">Select Sale Tax</option>';
		availableTaxes.forEach(function(t) {
			let taxRate = t.rate !== undefined ? t.rate : (t.tax_rate || 0);
			optionsPurchaseTaxes += `<option value="${t.id}" data-rate="${taxRate}">${t.name} (${taxRate}%)</option>`;
			optionsSaleTaxes += `<option value="${t.id}" data-rate="${taxRate}">${t.name} (${taxRate}%)</option>`;
		});

		return `
		<div class="card purchase-item-card mb-4" id="item_card_${index}" data-index="${index}">
			<div class="item-card-header">
				<h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-pills text-primary mr-2"></i> Purchase Item #<span class="item-number-badge">${index + 1}</span></h5>
				<button type="button" class="btn btn-danger btn-sm btn-remove-item" data-index="${index}">
					<i class="fas fa-trash-alt mr-1"></i> Remove Item
				</button>
			</div>
			<div class="card-body">
				<!-- Row 1: Product, Category -->
				<div class="row mb-3">
					<div class="col-lg-6">
						<div class="form-group">
							<label class="font-weight-bold">Medicine <span class="text-danger">*</span></label>
							<select class="form-control item-product-select select2" name="purchases[${index}][product]" data-index="${index}" required>
								${optionsProducts}
							</select>
						</div>
					</div>
					<div class="col-lg-6">
						<div class="form-group">
							<label class="font-weight-bold">Category <span class="text-danger">*</span></label>
							<select class="form-control item-category-select select2" name="purchases[${index}][category]" data-index="${index}" ${productPreselectId ? '' : 'disabled'} required>
								<option value="">Select category</option>
							</select>
						</div>
					</div>
				</div>

				<!-- Row 2: Pricing & Qty -->
				<div class="row mb-3">
					<div class="col-lg-4">
						<div class="form-group">
							<label class="font-weight-bold">Unit Cost Price <span class="text-danger">*</span></label>
							<input class="form-control item-cost-price" type="number" step="0.01" min="0.01" name="purchases[${index}][unit_cost_price]" data-index="${index}" required placeholder="0.00">
						</div>
					</div>
					<div class="col-lg-4">
						<div class="form-group">
							<label class="font-weight-bold">Paid Unit Cost Price <span class="text-danger">*</span></label>
							<input class="form-control item-paid-cost-price" type="number" step="0.01" min="0" name="purchases[${index}][paid_unit_cost_price]" data-index="${index}" required placeholder="0.00">
							<small class="text-success fw-bold d-block mt-1">
								Sales Tax Paid Per Unit: <span class="lbl-extra-per-unit">0.00</span> (<span class="lbl-extra-percent">0</span>%)
							</small>
							<input type="hidden" class="item-extra-per-unit" name="purchases[${index}][extra_paid_per_unit]" value="0">
							<input type="hidden" class="item-extra-percent" name="purchases[${index}][extra_paid_percent]" value="0">
						</div>
					</div>
					<div class="col-lg-4">
						<div class="form-group">
							<label class="font-weight-bold">Quantity <span class="text-danger">*</span></label>
							<input class="form-control item-quantity" type="number" min="1" step="1" name="purchases[${index}][quantity]" data-index="${index}" required placeholder="1">
							<small class="text-primary fw-bold d-block mt-1">
								Total Sales Tax Paid Amount: <span class="lbl-total-extra-paid">0.00</span>
							</small>
						</div>
					</div>
				</div>

				<!-- Row 3: Dates & Batch -->
				<div class="row mb-3">
					<div class="col-lg-4">
						<div class="form-group">
							<label class="font-weight-bold">Manufacturing Date</label>
							<input class="form-control" type="date" name="purchases[${index}][manufacturing_date]">
						</div>
					</div>
					<div class="col-lg-4">
						<div class="form-group">
							<label class="font-weight-bold">Expire Date <span class="text-danger">*</span></label>
							<input class="form-control" type="date" name="purchases[${index}][expiry_date]" required>
						</div>
					</div>
					<div class="col-lg-4">
						<div class="form-group">
							<label class="font-weight-bold">Batch No</label>
							<input class="form-control" type="text" name="purchases[${index}][batch_no]" placeholder="Batch number">
						</div>
					</div>
				</div>

				<!-- Row 4: Purchase Tax -->
				<div class="row mb-3">
					<div class="col-lg-12">
						<div class="form-group">
							<label class="font-weight-bold">Add Purchase Tax</label>
							<select class="form-control select2 item-tax-select" data-index="${index}">
								${optionsPurchaseTaxes}
							</select>
						</div>
						<div class="table-responsive">
							<table class="table table-bordered table-sm item-tax-table mb-0" data-index="${index}">
								<thead class="thead-light">
									<tr>
										<th>Tax Name</th>
										<th>Unit Tax Amount</th>
										<th>Total Tax Amount</th>
										<th style="width:10%;">Action</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
						<input type="hidden" class="item-unit-cost-tax-amount" name="purchases[${index}][unit_cost_tax_amount]" value="0">
						<input type="hidden" class="item-total-cost-tax-amount" name="purchases[${index}][total_cost_tax_amount]" value="0">
					</div>
				</div>

				<!-- Row 5: Unit Sale Price & Sale Tax -->
				<div class="row mb-2">
					<div class="col-lg-6">
						<div class="form-group">
							<label class="font-weight-bold">Unit Sale Price <span class="text-danger">*</span></label>
							<input class="form-control item-sale-price" type="number" step="0.01" name="purchases[${index}][unit_sale_price]" data-index="${index}" required placeholder="0.00">
						</div>
					</div>
					<div class="col-lg-6">
						<div class="form-group">
							<label class="font-weight-bold">Add Sale Tax</label>
							<select class="form-control select2 item-sale-tax-select" data-index="${index}" disabled>
								${optionsSaleTaxes}
							</select>
						</div>
					</div>
					<div class="col-lg-12">
						<div class="table-responsive">
							<table class="table table-bordered table-sm item-sale-tax-table mb-0" data-index="${index}">
								<thead class="thead-light">
									<tr>
										<th>Sale Tax Name</th>
										<th>Unit Sale Tax Amount</th>
										<th>Total Sale Tax Amount</th>
										<th style="width:10%;">Action</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
						<input type="hidden" class="item-unit-sale-tax-amount" name="purchases[${index}][unit_sale_tax_amount]" value="0">
						<input type="hidden" class="item-total-sale-tax-amount" name="purchases[${index}][total_sale_tax_amount]" value="0">
					</div>
				</div>
			</div>
		</div>
		`;
	}

	function initSelect2ForCard(card) {
		card.find('.select2').select2({
			width: '100%'
		});
	}

	function updateItemNumberBadges() {
		$('#purchaseItemsContainer .purchase-item-card').each(function(idx) {
			$(this).find('.item-number-badge').text(idx + 1);
		});

		// Ensure at least 1 card cannot be deleted
		let totalCards = $('#purchaseItemsContainer .purchase-item-card').length;
		if (totalCards <= 1) {
			$('.btn-remove-item').prop('disabled', true);
		} else {
			$('.btn-remove-item').prop('disabled', false);
		}
	}

	function addNewItemCard(productPreselectId) {
		let index = itemCardCounter++;
		let html = createPurchaseItemCardHtml(index, productPreselectId);
		$('#purchaseItemsContainer').append(html);
		let card = $(`#item_card_${index}`);
		initSelect2ForCard(card);
		updateItemNumberBadges();

		if (productPreselectId) {
			card.find('.item-product-select').trigger('change');
		}
	}

	function recalcCardPaidValues(card) {
		let cost = parseFloat(card.find('.item-cost-price').val()) || 0;
		let paid = parseFloat(card.find('.item-paid-cost-price').val()) || 0;
		let qty  = parseInt(card.find('.item-quantity').val()) || 0;

		let extra = paid - cost;
		if (extra < 0) extra = 0;

		let percent = cost > 0 ? (extra / cost) * 100 : 0;
		let totalPaid = extra * qty;

		card.find('.lbl-extra-per-unit').text(extra.toFixed(2));
		card.find('.lbl-extra-percent').text(percent.toFixed(2));
		card.find('.lbl-total-extra-paid').text(totalPaid.toFixed(2));

		card.find('.item-extra-per-unit').val(extra.toFixed(2));
		card.find('.item-extra-percent').val(percent.toFixed(2));
	}

	function recalcCardPurchaseTaxes(card) {
		let cost = parseFloat(card.find('.item-cost-price').val()) || 0;
		let qty  = parseInt(card.find('.item-quantity').val()) || 0;
		let idx  = card.data('index');

		let unitSum = 0;
		let totalSum = 0;

		card.find('.item-tax-table tbody tr').each(function() {
			let rate = parseFloat($(this).data('rate')) || 0;
			let taxId = $(this).data('tax-id');

			let unitTax = (cost * rate) / 100;
			let totalTax = (cost * qty * rate) / 100;

			unitSum += unitTax;
			totalSum += totalTax;

			$(this).find('td').eq(1).html(unitTax.toFixed(2) + `<input type="hidden" name="purchases[${idx}][taxes][${taxId}][unit]" value="${unitTax.toFixed(2)}">`);
			$(this).find('td').eq(2).html(totalTax.toFixed(2) + `<input type="hidden" name="purchases[${idx}][taxes][${taxId}][total]" value="${totalTax.toFixed(2)}">`);
		});

		card.find('.item-unit-cost-tax-amount').val(unitSum.toFixed(2));
		card.find('.item-total-cost-tax-amount').val(totalSum.toFixed(2));
	}

	function recalcCardSaleTaxes(card) {
		let unit = parseFloat(card.find('.item-sale-price').val()) || 0;
		let qty  = parseInt(card.find('.item-quantity').val()) || 0;
		let idx  = card.data('index');

		let unitSum = 0;
		let totalSum = 0;

		card.find('.item-sale-tax-table tbody tr').each(function() {
			let rate = parseFloat($(this).data('rate')) || 0;
			let taxId = $(this).data('tax-id');

			let unitTax = (unit * rate) / 100;
			let totalTax = (unit * qty * rate) / 100;

			unitSum += unitTax;
			totalSum += totalTax;

			$(this).find('td').eq(1).html(unitTax.toFixed(2) + `<input type="hidden" name="purchases[${idx}][sale_taxes][${taxId}][unit]" value="${unitTax.toFixed(2)}">`);
			$(this).find('td').eq(2).html(totalTax.toFixed(2) + `<input type="hidden" name="purchases[${idx}][sale_taxes][${taxId}][total]" value="${totalTax.toFixed(2)}">`);
		});

		card.find('.item-unit-sale-tax-amount').val(unitSum.toFixed(2));
		card.find('.item-total-sale-tax-amount').val(totalSum.toFixed(2));
	}

	$(document).ready(function() {
		// Initialize main supplier select2 tag box
		$('#main_supplier').select2({
			tags: true,
			width: '100%'
		});

		// Add initial item card
		addNewItemCard(preselectedProductId);

		// Handle Add Another Item Button
		$('#btnAddPurchaseItem').on('click', function() {
			addNewItemCard('');
		});

		// Handle Remove Item Button
		$(document).on('click', '.btn-remove-item', function() {
			let index = $(this).data('index');
			$(`#item_card_${index}`).remove();
			updateItemNumberBadges();
		});

		// Dynamic product -> category load
		$(document).on('change', '.item-product-select', function() {
			let card = $(this).closest('.purchase-item-card');
			let productId = $(this).val();
			let catSelect = card.find('.item-category-select');

			if (!productId) {
				catSelect.empty().append('<option value="">Select category</option>').prop('disabled', true).trigger('change');
				return;
			}

			$.ajax({
				url: '/admin/product/' + productId + '/categories',
				type: 'GET',
				success: function(data) {
					catSelect.empty().append('<option value="">Select category</option>');
					$.each(data, function(i, cat) {
						catSelect.append(`<option value="${cat.id}">${cat.name}</option>`);
					});
					catSelect.prop('disabled', false).trigger('change');
				}
			});
		});

		// Dynamic category -> prices fetch
		$(document).on('change', '.item-category-select', function(e) {
			let card = $(this).closest('.purchase-item-card');
			let productId = card.find('.item-product-select').val();
			let categoryId = $(this).val();

			if (!productId || !categoryId) return;

			$.ajax({
				url: '/admin/purchase/category-price',
				type: 'GET',
				data: { product_id: productId, category_id: categoryId },
				success: function(res) {
					card.find('.item-cost-price').val(res.unit_cost_price.toFixed(2));
					card.find('.item-paid-cost-price').val(res.paid_unit_cost_price.toFixed(2));
					card.find('.item-sale-price').val(res.unit_sale_price.toFixed(2));

					recalcCardPaidValues(card);
					recalcCardPurchaseTaxes(card);
					recalcCardSaleTaxes(card);

					let unitSale = parseFloat(res.unit_sale_price) || 0;
					if (unitSale > 0) {
						card.find('.item-sale-tax-select').prop('disabled', false);
					}
				}
			});
		});

		// Event handlers for prices and quantity inputs
		$(document).on('input', '.item-cost-price', function() {
			let card = $(this).closest('.purchase-item-card');
			let cost = parseFloat($(this).val()) || 0;
			let extra = parseFloat(card.find('.item-extra-per-unit').val()) || 0;

			let newPaid = cost + extra;
			card.find('.item-paid-cost-price').val(newPaid.toFixed(2));

			recalcCardPaidValues(card);
			recalcCardPurchaseTaxes(card);
		});

		$(document).on('input', '.item-paid-cost-price', function() {
			let card = $(this).closest('.purchase-item-card');
			recalcCardPaidValues(card);
		});

		$(document).on('change', '.item-paid-cost-price', function() {
			let card = $(this).closest('.purchase-item-card');
			let cost = parseFloat(card.find('.item-cost-price').val()) || 0;
			let paid = parseFloat($(this).val()) || 0;

			if (paid < cost) {
				$(this).val(cost.toFixed(2));
			}
			recalcCardPaidValues(card);
		});

		$(document).on('input', '.item-quantity', function() {
			let card = $(this).closest('.purchase-item-card');
			recalcCardPaidValues(card);
			recalcCardPurchaseTaxes(card);
			recalcCardSaleTaxes(card);
		});

		$(document).on('input', '.item-sale-price', function() {
			let card = $(this).closest('.purchase-item-card');
			let sale = parseFloat($(this).val()) || 0;
			let qty = parseInt(card.find('.item-quantity').val()) || 0;

			if (sale > 0 && qty > 0) {
				card.find('.item-sale-tax-select').prop('disabled', false);
			} else {
				card.find('.item-sale-tax-select').prop('disabled', true);
			}
			recalcCardSaleTaxes(card);
		});

		// Add Purchase Tax row to card
		$(document).on('change', '.item-tax-select', function() {
			let taxId = $(this).val();
			if (!taxId) return;

			let card = $(this).closest('.purchase-item-card');
			let idx = card.data('index');
			let option = $(this).find('option:selected');
			let taxName = option.text();
			let rate = parseFloat(option.data('rate')) || 0;

			if (card.find(`.item-tax-table tbody tr[data-tax-id="${taxId}"]`).length) {
				$(this).val('').trigger('change');
				return;
			}

			let row = `
				<tr data-tax-id="${taxId}" data-rate="${rate}">
					<td>
						${taxName}
						<input type="hidden" name="purchases[${idx}][taxes][${taxId}][id]" value="${taxId}">
						<input type="hidden" name="purchases[${idx}][taxes][${taxId}][rate]" value="${rate}">
					</td>
					<td>0.00</td>
					<td>0.00</td>
					<td><button type="button" class="btn btn-danger btn-sm btn-remove-tax">Delete</button></td>
				</tr>
			`;
			card.find('.item-tax-table tbody').append(row);
			$(this).val('').trigger('change');
			recalcCardPurchaseTaxes(card);
		});

		// Delete Purchase Tax row
		$(document).on('click', '.btn-remove-tax', function() {
			let card = $(this).closest('.purchase-item-card');
			$(this).closest('tr').remove();
			recalcCardPurchaseTaxes(card);
		});

		// Add Sale Tax row to card
		$(document).on('change', '.item-sale-tax-select', function() {
			let taxId = $(this).val();
			if (!taxId) return;

			let card = $(this).closest('.purchase-item-card');
			let idx = card.data('index');
			let option = $(this).find('option:selected');
			let taxName = option.text();
			let rate = parseFloat(option.data('rate')) || 0;

			if (card.find(`.item-sale-tax-table tbody tr[data-tax-id="${taxId}"]`).length) {
				$(this).val('').trigger('change');
				return;
			}

			let row = `
				<tr data-tax-id="${taxId}" data-rate="${rate}">
					<td>
						${taxName}
						<input type="hidden" name="purchases[${idx}][sale_taxes][${taxId}][id]" value="${taxId}">
						<input type="hidden" name="purchases[${idx}][sale_taxes][${taxId}][rate]" value="${rate}">
					</td>
					<td>0.00</td>
					<td>0.00</td>
					<td><button type="button" class="btn btn-danger btn-sm btn-remove-sale-tax">Delete</button></td>
				</tr>
			`;
			card.find('.item-sale-tax-table tbody').append(row);
			$(this).val('').trigger('change');
			recalcCardSaleTaxes(card);
		});

		// Delete Sale Tax row
		$(document).on('click', '.btn-remove-sale-tax', function() {
			let card = $(this).closest('.purchase-item-card');
			$(this).closest('tr').remove();
			recalcCardSaleTaxes(card);
		});

		// Form submit validation
		$('#multiplePurchaseForm').on('submit', function(e) {
			let hasError = false;
			$('.purchase-item-card').each(function(idx) {
				let card = $(this);
				let cost = parseFloat(card.find('.item-cost-price').val()) || 0;
				let paid = parseFloat(card.find('.item-paid-cost-price').val()) || 0;
				let sale = parseFloat(card.find('.item-sale-price').val()) || 0;

				if (sale <= paid) {
					alert(`Item #${idx + 1}: Unit Sale Price must be greater than Paid Unit Cost Price!`);
					card.find('.item-sale-price').focus();
					hasError = true;
					return false;
				}
			});

			if (hasError) {
				e.preventDefault();
				return false;
			}
		});
	});
</script>
@endpush
