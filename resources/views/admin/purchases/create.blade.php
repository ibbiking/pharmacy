@extends('admin.layouts.app')

@push('page-css')
<!-- Datetimepicker CSS -->
<link rel="stylesheet" href="{{asset('assets/css/bootstrap-datetimepicker.min.css')}}">
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Add Purchase</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Add Purchase</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">

				<!-- Add Medicine -->
				<form method="post" enctype="multipart/form-data" autocomplete="off"
					action="{{route('purchases.store')}}">
					@csrf
					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-4">
								<div class="form-group">
									<label>Medicine <span class="text-danger">*</span></label>
									<select class="select2 form-select form-control" name="product" id="product">
										<option value=""></option>
										@foreach ($products as $product)
										<option value="{{$product->id}}">{{$product->product_name}}</option>
										@endforeach
									</select>
								</div>
							</div>
							<div class="col-lg-4">
								<div class="form-group">
									<label>Category <span class="text-danger">*</span></label>
									<select class="select2 form-select form-control" name="category" id="category">
										<option value=""></option>
										@foreach ($categories as $category)
										<option value="{{$category->id}}">{{$category->name}}</option>
										@endforeach
									</select>
								</div>
							</div>
							<div class="col-lg-4">
								<div class="form-group">
									<label>Supplier <span class="text-danger">*</span></label>
									<select class="select2 form-select form-control" name="supplier" id="supplier">
										<option value=""></option>
										@foreach ($suppliers as $supplier)
										<option value="{{$supplier->id}}">{{$supplier->name}}</option>
										@endforeach
									</select>
								</div>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label>Unit Cost Price<span class="text-danger">*</span></label>
									<input class="form-control" type="number" name="unit_cost_price"
										id="unit_cost_price" step="0.01" value="{{ old('unit_cost_price') }}">
								</div>
							</div>
							<div class="col-lg-6">
								<div class="form-group">
									<label>Quantity<span class="text-danger">*</span></label>
									<input class="form-control" type="number" name="quantity" id="quantity" step="1"
										value="{{ old('quantity') }}">
								</div>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label>Expire Date<span class="text-danger">*</span></label>
									<input class="form-control" type="date" name="expiry_date"
										value="{{ old('expiry_date') }}">
								</div>
							</div>
							<div class="col-lg-6">
								<div class="form-group">
									<label>Medicine Image</label>
									<input type="file" name="image" class="form-control">
								</div>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label>Batch no<span class="text-danger">*</span></label>
									<input class="form-control" type="text" name="batch_no"
										value="{{ old('batch_no') }}">
								</div>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-12">
								<div class="form-group">
									<label>Add Tax <span class="text-danger">*</span></label>
									<select class="select2 form-select form-control" id="tax_select" disabled>
										<option value=""></option>
										@foreach ($taxes as $tax)
										<option value="{{$tax->id}}" data-rate="{{$tax->rate}}">{{$tax->name}} -
											{{$tax->rate}}%</option>
										@endforeach
									</select>
								</div>
							</div>
						</div>

						<!-- Table for showing selected taxes -->
						<div class="row mt-3">
							<div class="col-lg-12">
								<table class="table table-bordered" id="tax_table">
									<thead>
										<tr>
											<th>Tax Name</th>
											<th>Unit Tax</th>
											<th>Total Tax</th>
											<th>Action</th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- hidden sums (will be updated by JS) -->
					<input type="hidden" name="unit_cost_tax_amount" id="unit_cost_tax_amount" value="0">
					<input type="hidden" name="total_cost_tax_amount" id="total_cost_tax_amount" value="0">

					<hr>
					<h4>Sale Information</h4>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-6">
								<div class="form-group">
									<label>Unit Sale Price<span class="text-danger">*</span></label>
									<input class="form-control" type="number" step="0.01" name="unit_sale_price"
										id="unit_sale_price" value="{{ old('unit_sale_price') }}">
								</div>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-12">
								<div class="form-group">
									<label>Add Sale Tax</label>
									<select class="select2 form-select form-control" id="sale_tax_select" disabled>
										<option value=""></option>
										@foreach ($taxes as $tax)
										<option value="{{$tax->id}}" data-rate="{{$tax->rate}}">{{$tax->name}} -
											{{$tax->rate}}%</option>
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
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

					<input type="hidden" name="unit_sale_tax_amount" id="unit_sale_tax_amount" value="0">
					<input type="hidden" name="total_sale_tax_amount" id="total_sale_tax_amount" value="0">

					<div class="submit-section">
						<button class="btn btn-success submit-btn" type="submit">Submit</button>
					</div>
				</form>
				<!-- /Add Medicine -->
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
</script>
@endpush