@extends('admin.layouts.app')

@push('page-css')
<link rel="stylesheet" href="{{asset('assets/css/bootstrap-datetimepicker.min.css')}}">
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Purchase</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Edit Purchase</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">

				<form method="post" enctype="multipart/form-data" action="{{route('purchases.update',$purchase)}}">
					@csrf
					@method("PUT")

					<div class="row">
						<div class="col-lg-4">
							<div class="form-group">
								<label>Medicine <span class="text-danger">*</span></label>
								<select class="select2 form-select form-control" name="product" id="product">
									@foreach ($products as $product)
									<option value="{{$product->id}}" {{ $purchase->product_id == $product->id ?
										'selected' : '' }}>
										{{$product->product_name}}
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
									<option value="{{$category->id}}" {{ $purchase->category_id == $category->id ?
										'selected' : '' }}>
										{{$category->name}}
									</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-lg-4">
							<div class="form-group">
								<label>Supplier <span class="text-danger">*</span></label>
								<select class="select2 form-select form-control" name="supplier" id="supplier">
									@foreach ($suppliers as $supplier)
									<option value="{{$supplier->id}}" {{ $purchase->supplier_id == $supplier->id ?
										'selected' : '' }}>
										{{$supplier->name}}
									</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Unit Cost Price<span class="text-danger">*</span></label>
								<input class="form-control" type="number" step="0.01" name="unit_cost_price"
									id="unit_cost_price" value="{{$purchase->unit_cost_price}}">
							</div>
						</div>
						<div class="col-lg-6">
							<div class="form-group">
								<label>Paid Unit Cost Price <span class="text-danger">*</span></label>
								<input class="form-control" type="number" step="0.01" name="paid_unit_cost_price"
									id="paid_unit_cost_price" value="{{ $purchase->paid_unit_cost_price }}">

								<small class="text-success fw-bold">
									Sales Tax Paid Per Unit:
									<span id="extra_per_unit">0.00</span>
									(<span id="extra_percent">0</span>%)
								</small>
							</div>
						</div>
						<div class="col-lg-6">
							<div class="form-group">
								<label>Quantity<span class="text-danger">*</span></label>
								<input class="form-control" type="number" name="quantity" id="quantity"
									value="{{$purchase->quantity}}">
								<small class="text-primary fw-bold">
									Total Sales Tax Paid Amount: <span id="total_extra_paid_amount">0.00</span>
								</small>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Expire Date<span class="text-danger">*</span></label>
								<input class="form-control" type="date" name="expiry_date"
									value="{{$purchase->expiry_date}}">
							</div>
						</div>
						<div class="col-lg-6">
							<div class="form-group">
								<label>Batch no<span class="text-danger">*</span></label>
								<input class="form-control" type="text" name="batch_no" value="{{$purchase->batch_no}}">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Medicine Image</label>
						<input type="file" name="image" class="form-control">
						@if($purchase->image)
						<img src="{{asset('storage/purchases/'.$purchase->image)}}" alt="" class="img-thumbnail mt-2"
							width="100">
						@endif
					</div>

					<div class="form-group">
						<label>Add Tax</label>
						<select class="select2 form-select form-control" id="tax_select">
							<option value=""></option>
							@foreach ($taxes as $tax)
							<option value="{{$tax->id}}" data-rate="{{$tax->rate}}" {{ $purchase->
								taxes->contains('tax_id',$tax->id) ? 'disabled' : '' }}>
								{{$tax->name}} - {{$tax->rate}}%
							</option>
							@endforeach
						</select>
					</div>

					<table class="table table-bordered" id="tax_table">
						<thead>
							<tr>
								<th>Tax Name</th>
								<th>Unit Tax</th>
								<th>Total Tax</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							@foreach ($purchase->taxes as $ptax)
							<tr data-tax-id="{{$ptax->tax_id}}">
								<td>
									{{ optional($ptax->tax)->name ?? 'Unknown Tax' }}
									<input type="hidden" name="taxes[{{$ptax->tax_id}}][id]" value="{{$ptax->tax_id}}">
									<input type="hidden" name="taxes[{{$ptax->tax_id}}][rate]"
										value="{{ optional($ptax->tax)->rate }}">
								</td>
								<td>{{$ptax->tax_unit_amount}}
									<input type="hidden" name="taxes[{{$ptax->tax_id}}][unit]"
										value="{{$ptax->tax_unit_amount}}">
								</td>
								<td>{{$ptax->tax_amount}}
									<input type="hidden" name="taxes[{{$ptax->tax_id}}][total]"
										value="{{$ptax->tax_amount}}">
								</td>
								<td><button type="button" class="btn btn-danger btn-sm remove-tax">Delete</button></td>
							</tr>
							@endforeach
						</tbody>
					</table>

					<input type="hidden" name="unit_cost_tax_amount" id="unit_cost_tax_amount"
						value="{{$purchase->unit_cost_tax_amount}}">
					<input type="hidden" name="total_cost_tax_amount" id="total_cost_tax_amount"
						value="{{$purchase->total_cost_tax_amount}}">

					<hr>
					<h4>Sale Information</h4>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label>Unit Sale Price<span class="text-danger">*</span></label>
									<input class="form-control" type="number" step="0.01" name="unit_sale_price"
										id="unit_sale_price"
										value="{{ old('unit_sale_price', $purchase->unit_sale_price) }}">
								</div>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-12">
								<div class="form-group">
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

						<div class="row mt-3">
							<div class="col-lg-12">
								<table class="table table-bordered" id="sale_tax_table">
									<thead>
										<tr>
											<th>Tax Name</th>
											<th>Unit Tax</th>
											<th>Total Tax</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody>
										@foreach ($purchase->Saletaxes as $tax)
										<tr data-tax-id="{{ $tax->tax_id }}">
											<td>
												{{ $tax->tax->name }} ({{ $tax->tax_rate }}%)
												<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][id]"
													value="{{ $tax->tax_id }}">
												<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][rate]"
													value="{{ $tax->tax_rate }}">
											</td>
											<td>
												{{ number_format($tax->tax_unit_amount, 2) }}
												<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][unit]"
													value="{{ $tax->tax_unit_amount }}">
											</td>
											<td>
												{{ number_format($tax->tax_amount, 2) }}
												<input type="hidden" name="sale_taxes[{{ $tax->tax_id }}][total]"
													value="{{ $tax->tax_amount }}">
											</td>
											<td>
												<button type="button"
													class="btn btn-danger btn-sm remove-sale-tax">Delete</button>
											</td>
										</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<input type="hidden" name="unit_sale_tax_amount" id="unit_sale_tax_amount"
						value="{{ old('unit_sale_tax_amount', $purchase->unit_sale_tax_amount) }}">
					<input type="hidden" name="total_sale_tax_amount" id="total_sale_tax_amount"
						value="{{ old('total_sale_tax_amount', $purchase->total_sale_tax_amount) }}">
					<input type="hidden" name="extra_paid_per_unit" id="extra_paid_per_unit"
						value="{{ $purchase->extra_paid_per_unit }}">

					<input type="hidden" name="extra_paid_percent" id="extra_paid_percent"
						value="{{ $purchase->extra_paid_percent }}">

					<input type="hidden" name="paid_extra_total_cost_price" id="paid_extra_total_cost_price"
						value="{{ $purchase->paid_extra_total_cost_price }}">

					<div class="submit-section">
						<button class="btn btn-success submit-btn" type="submit">Update</button>
					</div>
				</form>

			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script src="{{asset('assets/js/moment.min.js')}}"></script>
<script src="{{asset('assets/js/bootstrap-datetimepicker.min.js')}}"></script>
<script>
	$(document).ready(function () {
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

	$('#product').on('change', function () {
    let productId = $(this).val();
    if (!productId) {
        $('#category').empty().append('<option value="">Select category</option>');
        return;
    }

    $.ajax({
        url: '/admin/product/' + productId + '/categories',
        type: 'GET',
        success: function (data) {
            $('#category').empty().append('<option value="">Select category</option>');
            $.each(data, function (i, cat) {
                $('#category').append('<option value="'+cat.id+'">'+cat.name+'</option>');
            });

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

    // Paid can NEVER be less than cost
    if (paid < cost && paid !== 0) {
        paid = cost;
        $('#paid_unit_cost_price').val(cost.toFixed(2));
    }

    let extra = paid - cost;
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

// Auto copy cost → paid if empty
$('#unit_cost_price').on('input', function () {
    let cost = parseFloat($(this).val()) || 0;

    if ($('#paid_unit_cost_price').val() === '') {
        $('#paid_unit_cost_price').val(cost.toFixed(2));
    }

    recalcPaidValues();
});

// Validate Paid
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

// Initial calculation when edit page loads
$(document).ready(function () {
    recalcPaidValues();
});
</script>
@endpush