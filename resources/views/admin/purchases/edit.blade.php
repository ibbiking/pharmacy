@extends('admin.layouts.app')

@push('page-css')
<link rel="stylesheet" href="{{asset('assets/css/bootstrap-datetimepicker.min.css')}}">
<style>
	.purchase-section-card {
		border: 1px solid #e3e8ee;
		border-radius: 8px;
		background: #ffffff;
		box-shadow: 0 2px 6px rgba(0,0,0,0.02);
		margin-bottom: 1.5rem;
	}
	.purchase-section-card .card-header {
		border-top-left-radius: 8px;
		border-top-right-radius: 8px;
		padding: 12px 20px;
	}
	.form-group label {
		font-weight: 600;
		color: #334155;
	}
</style>
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Purchase</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('purchases.index')}}">Purchases</a></li>
		<li class="breadcrumb-item active">Edit Purchase</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<form method="post" enctype="multipart/form-data" action="{{route('purchases.update',$purchase)}}">
			@csrf
			@method("PUT")

			<!-- Section 1: Medicine & Supplier Details -->
			<div class="card purchase-section-card shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
				<div class="card-header text-white py-3" style="background: linear-gradient(135deg, #0056b3, #007bff);">
					<h5 class="card-title text-white mb-1"><i class="fas fa-pills mr-2"></i> Medicine & Supplier Details</h5>
					<small class="text-white-50 font-weight-normal d-block">Select target medicine, packaging category level, and supplier vendor.</small>
				</div>
				<div class="card-body p-4">
					<div class="row">
						<div class="col-lg-4">
							<div class="form-group">
								<label>Medicine <span class="text-danger">*</span></label>
								<select class="select2 form-select form-control" name="product" id="product">
									@foreach ($products as $product)
									<option value="{{$product->id}}" {{ old('product', $purchase->product_id) == $product->id ? 'selected' : '' }}>
										{{$product->product_name}}{{ $product->strength ? ' (' . $product->strength->name . ')' : '' }}
									</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="form-group">
								<label>Category <span class="text-danger">*</span></label>
								<select class="select2 form-select form-control" name="category" id="category">
									@foreach ($categories as $category)
									<option value="{{$category->id}}" {{ old('category', $purchase->category_id) == $category->id ? 'selected' : '' }}>
										{{$category->name}}
									</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="form-group">
								<label>Supplier <span class="text-danger">*</span></label>
								@php
									$oldSupplier = old('supplier', $purchase->supplier_id);
								@endphp
								<select class="select2-tags-single form-control" name="supplier" id="supplier" data-placeholder="Select or Type New Supplier" required>
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
					</div>
				</div>
			</div>

			<!-- Section 2: Pricing, Quantity & Dates -->
			<div class="card purchase-section-card shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #17a2b8;">
				<div class="card-header text-white py-3" style="background: linear-gradient(135deg, #117a8b, #17a2b8);">
					<h5 class="card-title text-white mb-1"><i class="fas fa-tags mr-2"></i> Purchase Pricing & Stock Details</h5>
					<small class="text-white-50 font-weight-normal d-block">Unit cost price, paid cost price, batch number, expiry date, and stock quantity.</small>
				</div>
				<div class="card-body p-4">
					<div class="row">
						<div class="col-lg-4">
							<div class="form-group">
								<label>Unit Cost Price <span class="text-danger">*</span></label>
								<input class="form-control" type="number" step="0.01" name="unit_cost_price" id="unit_cost_price" value="{{ old('unit_cost_price', $purchase->unit_cost_price) }}" placeholder="0.00">
							</div>
						</div>
						<div class="col-lg-4">
							<div class="form-group">
								<label>Paid Unit Cost Price <span class="text-danger">*</span></label>
								<input class="form-control" type="number" step="0.01" name="paid_unit_cost_price" id="paid_unit_cost_price" value="{{ old('paid_unit_cost_price', $purchase->paid_unit_cost_price) }}" placeholder="0.00">
								<small class="text-success fw-bold d-block mt-1">
									Sales Tax Paid Per Unit: <span id="extra_per_unit">0.00</span> (<span id="extra_percent">0</span>%)
								</small>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="form-group">
								<label>Quantity <span class="text-danger">*</span></label>
								<input class="form-control" type="number" name="quantity" id="quantity" value="{{ old('quantity', $purchase->quantity) }}" placeholder="1">
								<small class="text-primary fw-bold d-block mt-1">
									Total Sales Tax Paid Amount: <span id="total_extra_paid_amount">0.00</span>
								</small>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-3">
							<div class="form-group">
								<label>Invoice No / Ref</label>
								<input class="form-control" type="text" name="invoice_no" value="{{ old('invoice_no', $purchase->invoice_no) }}" placeholder="Invoice number or reference">
							</div>
						</div>
						<div class="col-lg-3">
							<div class="form-group">
								<label>Manufacturing Date</label>
								<input class="form-control" type="date" name="manufacturing_date" value="{{ old('manufacturing_date', $purchase->manufacturing_date) }}">
							</div>
						</div>
						<div class="col-lg-3">
							<div class="form-group">
								<label>Expire Date <span class="text-danger">*</span></label>
								<input class="form-control" type="date" name="expiry_date" value="{{ old('expiry_date', $purchase->expiry_date) }}">
							</div>
						</div>
						<div class="col-lg-3">
							<div class="form-group">
								<label>Batch No</label>
								<input class="form-control" type="text" name="batch_no" value="{{ old('batch_no', $purchase->batch_no) }}" placeholder="Batch number">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-12">
							<div class="form-group mb-0">
								<label>Medicine Image</label>
								<input type="file" name="image" class="form-control">
								@if($purchase->image)
								<div class="mt-2">
									<img src="{{asset('storage/purchases/'.$purchase->image)}}" alt="Purchase image" class="img-thumbnail" width="100">
								</div>
								@endif
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Section 3: Purchase Tax Information -->
			<div class="card purchase-section-card shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #6c757d;">
				<div class="card-header bg-secondary text-white py-3">
					<h5 class="card-title text-white mb-1"><i class="fas fa-percentage mr-2"></i> Purchase Tax Information</h5>
					<small class="text-white-50 font-weight-normal d-block">Select purchase tax types to apply per unit and calculate total tax amounts.</small>
				</div>
				<div class="card-body p-4">
					<div class="row">
						<div class="col-lg-12">
							<div class="form-group">
								<label>Add Purchase Tax</label>
								<select class="select2 form-select form-control" id="tax_select">
									<option value=""></option>
									@foreach ($taxes as $tax)
									<option value="{{$tax->id}}" data-rate="{{$tax->rate}}" {{ $purchase->taxes->contains('tax_id',$tax->id) ? 'disabled' : '' }}>
										{{$tax->name}} - {{$tax->rate}}%
									</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="table-responsive">
						<table class="table table-bordered table-sm mb-0" id="tax_table">
							<thead class="thead-light">
								<tr>
									<th>Tax Name</th>
									<th>Unit Tax</th>
									<th>Total Tax</th>
									<th style="width:10%;">Action</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($purchase->taxes as $ptax)
								<tr data-tax-id="{{$ptax->tax_id}}">
									<td>
										{{ optional($ptax->tax)->name ?? 'Unknown Tax' }}
										<input type="hidden" name="taxes[{{$ptax->tax_id}}][id]" value="{{$ptax->tax_id}}">
										<input type="hidden" name="taxes[{{$ptax->tax_id}}][rate]" value="{{ optional($ptax->tax)->rate }}">
									</td>
									<td>
										{{$ptax->tax_unit_amount}}
										<input type="hidden" name="taxes[{{$ptax->tax_id}}][unit]" value="{{$ptax->tax_unit_amount}}">
									</td>
									<td>
										{{$ptax->tax_amount}}
										<input type="hidden" name="taxes[{{$ptax->tax_id}}][total]" value="{{$ptax->tax_amount}}">
									</td>
									<td><button type="button" class="btn btn-danger btn-sm remove-tax">Delete</button></td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<input type="hidden" name="unit_cost_tax_amount" id="unit_cost_tax_amount" value="{{ old('unit_cost_tax_amount', $purchase->unit_cost_tax_amount) }}">
			<input type="hidden" name="total_cost_tax_amount" id="total_cost_tax_amount" value="{{ old('total_cost_tax_amount', $purchase->total_cost_tax_amount) }}">

			<!-- Section 4: Sale & Sale Tax Information -->
			<div class="card purchase-section-card shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #28a745;">
				<div class="card-header text-white py-3" style="background: linear-gradient(135deg, #1e7e34, #28a745);">
					<h5 class="card-title text-white mb-1"><i class="fas fa-cash-register mr-2"></i> Sale Information & Sale Tax</h5>
					<small class="text-white-50 font-weight-normal d-block">Unit sale price setting and applicable customer sales tax rates.</small>
				</div>
				<div class="card-body p-4">
					<div class="row mb-3">
						<div class="col-lg-6">
							<div class="form-group mb-0">
								<label>Unit Sale Price <span class="text-danger">*</span></label>
								<input class="form-control" type="number" step="0.01" name="unit_sale_price" id="unit_sale_price" value="{{ old('unit_sale_price', $purchase->unit_sale_price) }}" placeholder="0.00">
							</div>
						</div>
						<div class="col-lg-6">
							<div class="form-group mb-0">
								<label>Add Sale Tax</label>
								<select class="select2 form-select form-control" id="sale_tax_select">
									<option value=""></option>
									@foreach ($taxes as $tax)
									<option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}">
										{{ $tax->name }} - {{ $tax->rate }}%
									</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="table-responsive">
						<table class="table table-bordered table-sm mb-0" id="sale_tax_table">
							<thead class="thead-light">
								<tr>
									<th>Tax Name</th>
									<th>Unit Tax</th>
									<th>Total Tax</th>
									<th style="width:10%;">Action</th>
								</tr>
							</thead>
							<tbody>
								@foreach ($purchase->Saletaxes as $tax)
								<tr data-tax-id="{{ $tax->tax_id }}">
									<td>
										{{ $tax->tax->name ?? 'Unknown Tax' }} ({{ $tax->tax_rate }}%)
										<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][id]" value="{{ $tax->tax_id }}">
										<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][rate]" value="{{ $tax->tax_rate }}">
									</td>
									<td>
										{{ number_format($tax->tax_unit_amount, 2) }}
										<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][unit]" value="{{ $tax->tax_unit_amount }}">
									</td>
									<td>
										{{ number_format($tax->tax_amount, 2) }}
										<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][total]" value="{{ $tax->tax_amount }}">
									</td>
									<td>
										<button type="button" class="btn btn-danger btn-sm remove-sale-tax">Delete</button>
									</td>
								</tr>
								@endforeach
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<input type="hidden" name="unit_sale_tax_amount" id="unit_sale_tax_amount" value="{{ old('unit_sale_tax_amount', $purchase->unit_sale_tax_amount) }}">
			<input type="hidden" name="total_sale_tax_amount" id="total_sale_tax_amount" value="{{ old('total_sale_tax_amount', $purchase->total_sale_tax_amount) }}">
			<input type="hidden" name="extra_paid_per_unit" id="extra_paid_per_unit" value="{{ $purchase->extra_paid_per_unit }}">
			<input type="hidden" name="extra_paid_percent" id="extra_paid_percent" value="{{ $purchase->extra_paid_percent }}">
			<input type="hidden" name="paid_extra_total_cost_price" id="paid_extra_total_cost_price" value="{{ $purchase->paid_extra_total_cost_price }}">

			<!-- Bottom Actions Bar -->
			<div class="card mb-4">
				<div class="card-body d-flex justify-content-between align-items-center">
					<a href="{{route('purchases.index')}}" class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
					<button class="btn btn-primary btn-lg px-4" type="submit"><i class="fas fa-sync-alt mr-1"></i> Update Purchase</button>
				</div>
			</div>
		</form>
	</div>
</div>
@endsection

@push('page-js')
<script src="{{asset('assets/js/moment.min.js')}}"></script>
<script src="{{asset('assets/js/bootstrap-datetimepicker.min.js')}}"></script>
<script>
	$(document).ready(function () {
		$('#supplier').select2({
			tags: true,
			width: '100%',
			tokenSeparators: [','],
			placeholder: $(this).attr('data-placeholder')
		});

		// cache ALL initial tax options so we can restore them later
		var taxCache = {};
		$('#tax_select option').each(function () {
			var v = $(this).val();
			if (!v) return; // skip empty first option
			taxCache[String(v)] = {
				text: $(this).text(),
				rate: $(this).data('rate')
			};
		});

		function updateTaxSums() {
			let unitSum = 0;
			let totalSum = 0;

			$('#tax_table tbody tr').each(function () {
				unitSum += parseFloat($(this).find('input[name*="[unit]"]').val()) || 0;
				totalSum += parseFloat($(this).find('input[name*="[total]"]').val()) || 0;
			});

			$('#unit_cost_tax_amount').val(unitSum.toFixed(2));
			$('#total_cost_tax_amount').val(totalSum.toFixed(2));
		}

		function recalcAllTaxRows() {
			// Recalculate unit/total tax for existing rows when unit/qty change
			let unit = parseFloat($('#unit_cost_price').val()) || 0;
			let qty  = parseInt($('#quantity').val()) || 0;

			$('#tax_table tbody tr').each(function () {
				var taxId = String($(this).data('tax-id'));
				var rate = parseFloat(taxCache[taxId].rate) || 0;

				var unitTax = (unit * rate) / 100;
				var totalTax = (unit * qty * rate) / 100;

				// update display + hidden inputs
				$(this).find('td').eq(1).html(unitTax.toFixed(2) + 
					' <input type="hidden" name="taxes['+taxId+'][unit]" value="'+unitTax.toFixed(2)+'">');
				$(this).find('td').eq(2).html(totalTax.toFixed(2) + 
					' <input type="hidden" name="taxes['+taxId+'][total]" value="'+totalTax.toFixed(2)+'">');
			});

			updateTaxSums();
		}

		// Enable tax dropdown when both unit price and quantity are entered
		$('#unit_cost_price, #quantity').on('input', function () {
			let unit = parseFloat($('#unit_cost_price').val()) || 0;
			let qty = parseInt($('#quantity').val()) || 0;
			if (unit > 0 && qty > 0) {
				$('#tax_select').prop('disabled', false);
			} else {
				$('#tax_select').prop('disabled', true);
			}

			// If taxes already added, recompute them live
			if ($('#tax_table tbody tr').length) {
				recalcAllTaxRows();
			}
		});

		// When selecting a tax
		$('#tax_select').on('change', function () {
			var taxId = $(this).val();
			if (!taxId) return;

			// prevent duplicates (extra guard)
			if ($('#tax_table tbody tr[data-tax-id="'+taxId+'"]').length) {
				// already added, clear selection and return
				$(this).val('').trigger('change');
				return;
			}

			var taxData = taxCache[String(taxId)];
			if (!taxData) {
				// should not happen, but guard
				$(this).val('').trigger('change');
				return;
			}

			var rate = parseFloat(taxData.rate) || 0;
			var unit = parseFloat($('#unit_cost_price').val()) || 0;
			var qty = parseInt($('#quantity').val()) || 0;
			if (!unit || !qty) {
				$(this).val('').trigger('change');
				return;
			}

			var unitTax = (unit * rate) / 100;
			var totalTax = (unit * qty * rate) / 100;

			// Append row
			var row = `
				<tr data-tax-id="${taxId}">
		<td>
			${taxData.text}
			<input type="hidden" name="taxes[${taxId}][id]" value="${taxId}">
			<input type="hidden" name="taxes[${taxId}][rate]" value="${rate}">
		</td>
		<td>${unitTax.toFixed(2)} <input type="hidden" name="taxes[${taxId}][unit]" value="${unitTax.toFixed(2)}"></td>
		<td>${totalTax.toFixed(2)} <input type="hidden" name="taxes[${taxId}][total]" value="${totalTax.toFixed(2)}"></td>
		<td><button type="button" class="btn btn-danger btn-sm remove-tax">Delete</button></td>
	</tr>
			`;
			$('#tax_table tbody').append(row);

			// Remove selected option from dropdown (so it can't be picked again)
			$('#tax_select option[value="'+taxId+'"]').remove();

			// Clear select2 selection
			if ($('#tax_select').hasClass('select2-hidden-accessible')) {
				$('#tax_select').val(null).trigger('change.select2');
			} else {
				$('#tax_select').val(null).trigger('change');
			}

			updateTaxSums();
		});

		// Remove tax row (restore option)
		$(document).on('click', '.remove-tax', function () {
			var row = $(this).closest('tr');
			var taxId = String(row.data('tax-id'));

			// restore option in dropdown from cache
			if (taxCache[taxId]) {
				var opt = new Option(taxCache[taxId].text, taxId, false, false);
				$(opt).attr('data-rate', taxCache[taxId].rate);
				$('#tax_select').append(opt);

				// refresh select2 if used
				if ($('#tax_select').hasClass('select2-hidden-accessible')) {
					$('#tax_select').trigger('change.select2');
				} else {
					$('#tax_select').trigger('change');
				}
			}

			// remove row
			row.remove();

			updateTaxSums();
		});
		if ($('#tax_table tbody tr').length) {
            recalcAllTaxRows();
        }

		// cache ALL initial sale tax options so we can restore them later
    var saleTaxCache = {};
    $('#sale_tax_select option').each(function () {
        var v = $(this).val();
        if (!v) return;
        saleTaxCache[String(v)] = {
            text: $(this).text(),
            rate: $(this).data('rate')
        };
    });

    function updateSaleTaxSums() {
        let unitSum = 0;
        let totalSum = 0;

        $('#sale_tax_table tbody tr').each(function () {
            unitSum += parseFloat($(this).find('input[name*="[unit]"]').val()) || 0;
            totalSum += parseFloat($(this).find('input[name*="[total]"]').val()) || 0;
        });

        $('#unit_sale_tax_amount').val(unitSum.toFixed(2));
        $('#total_sale_tax_amount').val(totalSum.toFixed(2));
    }

    function recalcAllSaleTaxRows() {
        let unit = parseFloat($('#unit_sale_price').val()) || 0;
        let qty  = parseInt($('#quantity').val()) || 0;

        $('#sale_tax_table tbody tr').each(function () {
            var taxId = String($(this).data('tax-id'));
            var rate = parseFloat(saleTaxCache[taxId].rate) || 0;

            var unitTax = (unit * rate) / 100;
            var totalTax = (unit * qty * rate) / 100;

            $(this).find('td').eq(1).html(unitTax.toFixed(2) + 
                ' <input type="hidden" name="sale_taxes['+taxId+'][unit]" value="'+unitTax.toFixed(2)+'">');
            $(this).find('td').eq(2).html(totalTax.toFixed(2) + 
                ' <input type="hidden" name="sale_taxes['+taxId+'][total]" value="'+totalTax.toFixed(2)+'">');
        });

        updateSaleTaxSums();
    }

    // Enable sale tax dropdown when unit sale price & base qty > 0
    $('#unit_sale_price, #quantity').on('input', function () {
        let unit = parseFloat($('#unit_sale_price').val()) || 0;
        let qty = parseInt($('#quantity').val()) || 0;
        if (unit > 0 && qty > 0) {
            $('#sale_tax_select').prop('disabled', false);
        } else {
            $('#sale_tax_select').prop('disabled', true);
        }

        if ($('#sale_tax_table tbody tr').length) {
            recalcAllSaleTaxRows();
        }
    });

    // Add sale tax row
    $('#sale_tax_select').on('change', function () {
        var taxId = $(this).val();
        if (!taxId) return;

        if ($('#sale_tax_table tbody tr[data-tax-id="'+taxId+'"]').length) {
            $(this).val('').trigger('change');
            return;
        }

        var taxData = saleTaxCache[String(taxId)];
        if (!taxData) {
            $(this).val('').trigger('change');
            return;
        }

        var rate = parseFloat(taxData.rate) || 0;
        var unit = parseFloat($('#unit_sale_price').val()) || 0;
        var qty = parseInt($('#quantity').val()) || 0;
        if (!unit || !qty) {
            $(this).val('').trigger('change');
            return;
        }

        var unitTax = (unit * rate) / 100;
        var totalTax = (unit * qty * rate) / 100;

        var row = `
            <tr data-tax-id="${taxId}">
                <td>
                    ${taxData.text}
                    <input type="hidden" name="sale_taxes[${taxId}][id]" value="${taxId}">
                    <input type="hidden" name="sale_taxes[${taxId}][rate]" value="${rate}">
                </td>
                <td>${unitTax.toFixed(2)} <input type="hidden" name="sale_taxes[${taxId}][unit]" value="${unitTax.toFixed(2)}"></td>
                <td>${totalTax.toFixed(2)} <input type="hidden" name="sale_taxes[${taxId}][total]" value="${totalTax.toFixed(2)}"></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-sale-tax">Delete</button></td>
            </tr>
        `;
        $('#sale_tax_table tbody').append(row);

        $('#sale_tax_select option[value="'+taxId+'"]').remove();
        $('#sale_tax_select').val(null).trigger('change');

        updateSaleTaxSums();
    });

    // Remove sale tax row
    $(document).on('click', '.remove-sale-tax', function () {
        var row = $(this).closest('tr');
        var taxId = String(row.data('tax-id'));

        if (saleTaxCache[taxId]) {
            var opt = new Option(saleTaxCache[taxId].text, taxId, false, false);
            $(opt).attr('data-rate', saleTaxCache[taxId].rate);
            $('#sale_tax_select').append(opt).trigger('change');
        }

        row.remove();
        updateSaleTaxSums();
    });

    if ($('#sale_tax_table tbody tr').length) {
        recalcAllSaleTaxRows();
    }
	});

	$('form').on('submit', function (e) {
		let cost = parseFloat($('#unit_cost_price').val()) || 0;
		let sale = parseFloat($('#unit_sale_price').val()) || 0;
		
		if (sale <= cost) {
			e.preventDefault();
			alert("Unit Sale Price must be greater than Unit Cost Price!");
			$('#unit_sale_price').focus();
			return false;
		}
	});

	$('#product').on('change', function () {
    let productId = $(this).val();
    if (!productId) {
        $('#category').empty().append('<option value="">Select category</option>');
        $('#category').prop('disabled', true);
        if ($('#category').hasClass('select2-hidden-accessible')) {
            $('#category').trigger('change.select2');
        }
        return;
    }

    $.ajax({
        url: '/admin/product/' + productId + '/categories',
        type: 'GET',
        success: function (data) {
            $('#category').empty().append('<option value="">Select category</option>');
            let oldCat = "{{ old('category', $purchase->category_id) }}";
            $.each(data, function (i, cat) {
                let sel = (oldCat == cat.id) ? 'selected' : '';
                $('#category').append('<option value="'+cat.id+'" '+sel+'>'+cat.name+'</option>');
            });

            $('#category').prop('disabled', false);

            // refresh select2 if applied
            if ($('#category').hasClass('select2-hidden-accessible')) {
                $('#category').trigger('change.select2');
            }
        }
    });
});

function recalcPaidValues() {
    let cost = parseFloat($('#unit_cost_price').val()) || 0;
    let paid = parseFloat($('#paid_unit_cost_price').val()) || 0;
    let qty  = parseInt($('#quantity').val()) || 0;

    let extra = paid - cost;
    if(extra < 0) extra = 0;

    let percent = cost > 0 ? (extra / cost) * 100 : 0;
    let totalPaid = extra * qty;

    $('#extra_per_unit').text(extra.toFixed(2));
    $('#extra_percent').text(percent.toFixed(2));
    $('#total_extra_paid_amount').text(totalPaid.toFixed(2));

    // Hidden DB values
    $('#extra_paid_per_unit').val(extra.toFixed(2));
    $('#extra_paid_percent').val(percent.toFixed(2));
    $('#paid_extra_total_cost_price').val(totalPaid.toFixed(2));
}

// Auto copy cost → paid maintaining identical padding
$('#unit_cost_price').on('input', function () {
    let cost = parseFloat($(this).val()) || 0;
    let currentExtra = parseFloat($('#extra_paid_per_unit').val()) || 0;

    if ($('#paid_unit_cost_price').val() === '') {
        currentExtra = 0;
    }

    let newPaid = cost + currentExtra;
    $('#paid_unit_cost_price').val(newPaid.toFixed(2));

    recalcPaidValues();
});

// Recomp cleanly as they type without locking
$('#paid_unit_cost_price').on('input', function () {
    recalcPaidValues();
});

// Validate Paid safety lock on blur
$('#paid_unit_cost_price').on('change', function () {
    let cost = parseFloat($('#unit_cost_price').val()) || 0;
    let paid = parseFloat($(this).val()) || 0;

    if (paid < cost) {
        $(this).val(cost.toFixed(2));
    }

    recalcPaidValues();
});

// Recalc on qty change
$('#quantity').on('input', function () {
    recalcPaidValues();
});

$('#category').on('change', function(e) {
    let productId = $('#product').val();
    let categoryId = $(this).val();

    if (!productId || !categoryId) return;

    let isUserChange = e.originalEvent !== undefined;

    // In edit purchase, ONLY fetch if the user manually changes the category.
    // Do not fetch prices if already have prices in the field (on load).
    if (isUserChange) {
        $.ajax({
            url: '/admin/purchase/category-price',
            type: 'GET',
            data: {
                product_id: productId,
                category_id: categoryId
            },
            success: function(res) {
                $('#unit_cost_price').val(res.unit_cost_price.toFixed(2));
                $('#paid_unit_cost_price').val(res.paid_unit_cost_price.toFixed(2));
                $('#unit_sale_price').val(res.unit_sale_price.toFixed(2));

                $('#unit_cost_price').trigger('input');
                $('#unit_sale_price').trigger('input');
            }
        });
    }
});

// Initial calculation when edit page loads
$(document).ready(function () {
    recalcPaidValues();
});
</script>
@endpush