<div class="modal fade" id="quickStockModal" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
	<div class="modal-dialog modal-dialog-centered modal-lg" role="document">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
			<div class="modal-header bg-light" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
				<h5 class="modal-title font-weight-bold">
					<i class="fas fa-box text-primary mr-2"></i> Add Stock: <span class="text-dark">{{ $product->product_name }}</span>
				</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body p-4">
				<form id="quick-stock-form" autocomplete="off">
					@csrf
					<input type="hidden" name="product" id="qs_product" value="{{ $product->id }}">
					<input type="hidden" name="unit_cost_tax_amount" id="qs_unit_cost_tax_amount" value="0">
					<input type="hidden" name="total_cost_tax_amount" id="qs_total_cost_tax_amount" value="0">
					<input type="hidden" name="unit_sale_tax_amount" id="qs_unit_sale_tax_amount" value="0">
					<input type="hidden" name="total_sale_tax_amount" id="qs_total_sale_tax_amount" value="0">
					<input type="hidden" name="extra_paid_per_unit" id="qs_extra_paid_per_unit" value="0">
					<input type="hidden" name="extra_paid_percent" id="qs_extra_paid_percent" value="0">
					<input type="hidden" name="paid_extra_total_cost_price" id="qs_paid_extra_total_cost_price" value="0">

					{{-- This view is fetched over AJAX and injected directly into the DOM
					     (see ProductController::quickStockModal) — it never passes through
					     admin.layouts.app, so @push('page-css') would silently vanish here.
					     Inlining the <style> tag is the correct approach for this fragment. --}}
					@include('admin.partials._purchase-section-styles')

					<!-- Sub-section: Category & Supplier (blue, matches Medicine & Supplier Details) -->
					<div class="card purchase-section-card purchase-section-card--medicine purchase-section-card--compact">
						<div class="card-header">
							<h6><i class="fas fa-pills mr-2"></i> Category & Supplier</h6>
						</div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-6">
									<label class="font-weight-bold text-muted small text-uppercase">Category <span class="text-danger">*</span></label>
									<select class="form-control qs-select2" name="category" id="qs_category" required>
										<option value=""></option>
										<!-- Categories will be loaded via AJAX from product -->
									</select>
								</div>
								<div class="col-md-6">
									<label class="font-weight-bold text-muted small text-uppercase">Supplier <span class="text-danger">*</span></label>
									<select class="form-control qs-select2" name="supplier" id="qs_supplier" data-placeholder="Select or Add New Supplier" required>
										<option value=""></option>
										@foreach ($suppliers as $supplier)
										<option value="{{$supplier->id}}">{{$supplier->name}}</option>
										@endforeach
									</select>
								</div>
							</div>
						</div>
					</div>

					<!-- Sub-section: Purchase Pricing & Stock Details (teal) -->
					<div class="card purchase-section-card purchase-section-card--pricing purchase-section-card--compact">
						<div class="card-header">
							<h6><i class="fas fa-tags mr-2"></i> Purchase Pricing & Stock Details</h6>
						</div>
						<div class="card-body">
							<div class="row form-group">
								<div class="col-md-4">
									<label class="font-weight-bold text-muted small text-uppercase">Unit Cost Price <span class="text-danger">*</span></label>
									<input class="form-control" type="number" name="unit_cost_price" id="qs_unit_cost_price" step="0.01" required>
								</div>
								<div class="col-md-4">
									<label class="font-weight-bold text-muted small text-uppercase">Paid Unit Cost <span class="text-danger">*</span></label>
									<input class="form-control border-primary" type="number" step="0.01" name="paid_unit_cost_price" id="qs_paid_unit_cost_price" required>
									<small class="text-success fw-bold mt-1 d-block" style="line-height:1.2; font-size:11px;">
										Sales Tax Paid Per Unit: <span id="qs_extra_per_unit">0.00</span> (<span id="qs_extra_percent">0</span>%)
									</small>
								</div>
								<div class="col-md-4">
									<label class="font-weight-bold text-muted small text-uppercase">Qty <span class="text-danger">*</span></label>
									<input class="form-control" type="number" name="quantity" id="qs_quantity" step="1" min="1" value="1" required>
									<small class="text-primary fw-bold mt-1 d-block" style="line-height:1.2; font-size:11px;">
										Tax Paid: <span id="qs_total_extra_paid_amount">0.00</span>
									</small>
								</div>
							</div>

							<div class="row form-group mb-0">
								<div class="col-md-3">
									<label class="font-weight-bold text-muted small text-uppercase" title="Optional">Mfg Date</label>
									<input class="form-control" type="date" name="manufacturing_date">
								</div>
								<div class="col-md-3">
									<label class="font-weight-bold text-muted small text-uppercase">Expiry <span class="text-danger">*</span></label>
									<input class="form-control" type="date" name="expiry_date" required>
								</div>
								<div class="col-md-3">
									<label class="font-weight-bold text-muted small text-uppercase">Inv No</label>
									<input class="form-control" type="text" name="invoice_no" placeholder="Opt">
								</div>
								<div class="col-md-3">
									<label class="font-weight-bold text-muted small text-uppercase">Batch</label>
									<input class="form-control" type="text" name="batch_no" placeholder="Opt">
								</div>
							</div>
						</div>
					</div>

					<!-- Sub-section: Purchase Tax Information (grey) -->
					<div class="card purchase-section-card purchase-section-card--tax purchase-section-card--compact">
						<div class="card-header">
							<h6><i class="fas fa-percentage mr-2"></i> Purchase Tax Information</h6>
						</div>
						<div class="card-body">
							<label class="font-weight-bold text-muted small text-uppercase">Add Purchase Tax</label>
							<select class="form-control qs-select2" id="qs_tax_select" disabled>
								<option value=""></option>
								@foreach ($taxes as $tax)
								<option value="{{$tax->id}}" data-rate="{{$tax->rate}}">{{$tax->name}} ({{$tax->rate}}%)</option>
								@endforeach
							</select>
							<div class="table-responsive mt-2">
								<table class="table table-sm table-bordered text-center" id="qs_tax_table">
									<thead class="bg-light"><tr><th class="small">Tax</th><th class="small">Value</th><th class="small">X</th></tr></thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- Sub-section: Sale Information & Sale Tax (green) -->
					<div class="card purchase-section-card purchase-section-card--sale purchase-section-card--compact mb-0">
						<div class="card-header">
							<h6><i class="fas fa-cash-register mr-2"></i> Sale Information & Sale Tax</h6>
						</div>
						<div class="card-body">
							<div class="row form-group">
								<div class="col-md-6">
									<label class="font-weight-bold text-muted small text-uppercase">Unit Sale Price <span class="text-danger">*</span></label>
									<input class="form-control border-success" type="number" step="0.01" name="unit_sale_price" id="qs_unit_sale_price" required>
								</div>
								<div class="col-md-6">
									<label class="font-weight-bold text-muted small text-uppercase">Add Sale Tax</label>
									<select class="form-control qs-select2" id="qs_sale_tax_select" disabled>
										<option value=""></option>
										@foreach ($taxes as $tax)
										<option value="{{$tax->id}}" data-rate="{{$tax->rate}}">{{$tax->name}} ({{$tax->rate}}%)</option>
										@endforeach
									</select>
								</div>
							</div>
							<div class="table-responsive mt-2">
								<table class="table table-sm table-bordered text-center" id="qs_sale_tax_table">
									<thead class="bg-light"><tr><th class="small">Tax</th><th class="small">Value</th><th class="small">X</th></tr></thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>

					<div id="qs_form_errors" class="alert alert-danger d-none small mt-3"></div>

				</form>
			</div>
			<div class="modal-footer bg-light" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary px-4" id="qs_submit_btn">Add Stock</button>
			</div>
		</div>
	</div>
</div>

<script>
	$(document).ready(function() {
		// Initialize Select2 mapping on QS elements 
        $('#qs_supplier').select2({ dropdownParent: $('#quickStockModal .modal-content'), width: '100%', placeholder: 'Select or Add New Supplier', tags: true, tokenSeparators: [','] });

		let productId = $('#qs_product').val();

		// Load Categories
		$.ajax({
			url: '/admin/product/' + productId + '/categories',
			type: 'GET',
			success: function (data) {
				$('#qs_category').empty().append('<option value=""></option>');
				$.each(data, function (i, cat) {
					$('#qs_category').append('<option value="'+cat.id+'">'+cat.name+'</option>');
				});
                $('#qs_category').select2({ dropdownParent: $('#quickStockModal .modal-content'), width: '100%', placeholder: 'Select Category' });
			}
		});

		// Fetch Prices on Category Change
		$('#qs_category').on('change', function(e) {
			let cid = $(this).val();
			if (!cid) {
				$('#qs_tax_select, #qs_sale_tax_select').prop('disabled', true);
				return;
			}
			
			// Enable taxes
			$('#qs_tax_select, #qs_sale_tax_select').prop('disabled', false);

			$.ajax({
				url: '/admin/purchase/category-price',
				type: 'GET',
				data: { product_id: productId, category_id: cid },
				success: function(res) {
					$('#qs_unit_cost_price').val(res.unit_cost_price.toFixed(2));
					$('#qs_paid_unit_cost_price').val(res.paid_unit_cost_price.toFixed(2));
					$('#qs_unit_sale_price').val(res.unit_sale_price.toFixed(2));

					$('#qs_unit_cost_price').trigger('input');
					$('#qs_unit_sale_price').trigger('input');
				}
			});
		});

        // ---------------------------------
        // Paid Cost Calculation logic
        // ---------------------------------
        function recalcQsPaidValues() {
            let cost = parseFloat($('#qs_unit_cost_price').val()) || 0;
            let paid = parseFloat($('#qs_paid_unit_cost_price').val()) || 0;
            let qty = parseInt($('#qs_quantity').val()) || 0;

            let extra = paid - cost;
            if (extra < 0) extra = 0;

            let percent = cost > 0 ? (extra / cost) * 100 : 0;
            let totalPaid = extra * qty;

            $('#qs_extra_per_unit').text(extra.toFixed(2));
            $('#qs_extra_percent').text(percent.toFixed(2));
            $('#qs_total_extra_paid_amount').text(totalPaid.toFixed(2));

            $('#qs_extra_paid_per_unit').val(extra.toFixed(2));
            $('#qs_extra_paid_percent').val(percent.toFixed(2));
            $('#qs_paid_extra_total_cost_price').val(totalPaid.toFixed(2));
        }

        $('#qs_unit_cost_price, #qs_quantity').on('input', function() {
            let cost = parseFloat($('#qs_unit_cost_price').val()) || 0;
            let currentExtra = parseFloat($('#qs_extra_paid_per_unit').val()) || 0;
            if ($('#qs_paid_unit_cost_price').val() === '') {
                currentExtra = 0;
            }
            $('#qs_paid_unit_cost_price').val((cost + currentExtra).toFixed(2));
            recalcQsPaidValues();
            recalcQsPurchaseTable();
        });

        $('#qs_paid_unit_cost_price').on('input', function() { recalcQsPaidValues(); });
        $('#qs_paid_unit_cost_price').on('change', function() {
            let cost = parseFloat($('#qs_unit_cost_price').val()) || 0;
            let paid = parseFloat($(this).val()) || 0;
            if (paid < cost) $(this).val(cost.toFixed(2));
            recalcQsPaidValues();
        });

        $('#qs_unit_sale_price').on('input', function() {
            recalcQsSaleTable();
        });

        // ---------------------------------
        // Tax Engines
        // ---------------------------------
        let qsPurchaseTaxes = [];
        let qsSaleTaxes = [];

		$('#qs_tax_select').select2({ dropdownParent: $('#quickStockModal .modal-content'), width: '100%', placeholder: 'Select Purchase Tax' });
		$('#qs_sale_tax_select').select2({ dropdownParent: $('#quickStockModal .modal-content'), width: '100%', placeholder: 'Select Sale Tax' });

        $('#qs_tax_select').on('change', function() {
            let id = $(this).val();
            if(!id) return;
            let opt = $(this).find(':selected');
            let name = opt.text();
            let rate = parseFloat(opt.data('rate'));
            $(this).val('').trigger('change.select2');
            opt.prop('disabled', true);
            qsPurchaseTaxes.push({id: id, name: name, rate: rate, opt: opt});
            recalcQsPurchaseTable();
        });

        $('#qs_sale_tax_select').on('change', function() {
            let id = $(this).val();
            if(!id) return;
            let opt = $(this).find(':selected');
            let name = opt.text();
            let rate = parseFloat(opt.data('rate'));
            $(this).val('').trigger('change.select2');
            opt.prop('disabled', true);
            qsSaleTaxes.push({id: id, name: name, rate: rate, opt: opt});
            recalcQsSaleTable();
        });

        $(document).on('click', '.qs-remove-tax', function() {
            let id = $(this).data('id');
            let type = $(this).data('type');
            if(type === 'purchase') {
                let ix = qsPurchaseTaxes.findIndex(t => t.id == id);
                if(ix > -1) {
                    qsPurchaseTaxes[ix].opt.prop('disabled', false);
                    qsPurchaseTaxes.splice(ix, 1);
                }
                recalcQsPurchaseTable();
            } else {
                let ix = qsSaleTaxes.findIndex(t => t.id == id);
                if(ix > -1) {
                    qsSaleTaxes[ix].opt.prop('disabled', false);
                    qsSaleTaxes.splice(ix, 1);
                }
                recalcQsSaleTable();
            }
        });

        function recalcQsPurchaseTable() {
            let tbody = $('#qs_tax_table tbody');
            tbody.empty();
            let cost = parseFloat($('#qs_unit_cost_price').val()) || 0;
            let qty = parseInt($('#qs_quantity').val()) || 0;
            let unitAcc = 0; let totalAcc = 0;

            qsPurchaseTaxes.forEach((t, i) => {
                let uAmt = (cost * t.rate) / 100;
                let tAmt = uAmt * qty;
                unitAcc += uAmt; totalAcc += tAmt;
                tbody.append(`<tr>
                    <td class="small align-middle">${t.name}
                        <input type="hidden" name="taxes[${i}][id]" value="${t.id}">
                        <input type="hidden" name="taxes[${i}][rate]" value="${t.rate}">
                        <input type="hidden" name="taxes[${i}][unit]" value="${uAmt.toFixed(2)}">
                        <input type="hidden" name="taxes[${i}][total]" value="${tAmt.toFixed(2)}">
                    </td>
                    <td class="small align-middle text-muted">${uAmt.toFixed(2)}</td>
                    <td class="p-1"><button type="button" class="btn btn-sm btn-outline-danger qs-remove-tax py-0 px-2" data-id="${t.id}" data-type="purchase"><i class="fas fa-times"></i></button></td>
                </tr>`);
            });
            $('#qs_unit_cost_tax_amount').val(unitAcc.toFixed(2));
            $('#qs_total_cost_tax_amount').val(totalAcc.toFixed(2));
        }

        function recalcQsSaleTable() {
            let tbody = $('#qs_sale_tax_table tbody');
            tbody.empty();
            let cost = parseFloat($('#qs_unit_sale_price').val()) || 0;
            let qty = parseInt($('#qs_quantity').val()) || 0;
            let unitAcc = 0; let totalAcc = 0;

            qsSaleTaxes.forEach((t, i) => {
                let uAmt = (cost * t.rate) / 100;
                let tAmt = uAmt * qty;
                unitAcc += uAmt; totalAcc += tAmt;
                tbody.append(`<tr>
                    <td class="small align-middle">${t.name}
                        <input type="hidden" name="sale_taxes[${i}][id]" value="${t.id}">
                        <input type="hidden" name="sale_taxes[${i}][rate]" value="${t.rate}">
                        <input type="hidden" name="sale_taxes[${i}][unit]" value="${uAmt.toFixed(2)}">
                        <input type="hidden" name="sale_taxes[${i}][total]" value="${tAmt.toFixed(2)}">
                    </td>
                    <td class="small align-middle text-muted">${uAmt.toFixed(2)}</td>
                    <td class="p-1"><button type="button" class="btn btn-sm btn-outline-danger qs-remove-tax py-0 px-2" data-id="${t.id}" data-type="sale"><i class="fas fa-times"></i></button></td>
                </tr>`);
            });
            $('#qs_unit_sale_tax_amount').val(unitAcc.toFixed(2));
            $('#qs_total_sale_tax_amount').val(totalAcc.toFixed(2));
        }

        // Submit via AJAX
        $('#qs_submit_btn').on('click', function() {
            let btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            $('#qs_form_errors').addClass('d-none').html('');

            $.ajax({
                url: "{{ route('purchases.store') }}",
                type: "POST",
                data: $('#quick-stock-form').serialize(),
                success: function(res) {
                    if(res.success) {
                        $('#quickStockModal').modal('hide');
                        // Reload datatable if it exists
                        if(typeof $('#product-table').DataTable === 'function') {
                            $('#product-table').DataTable().ajax.reload(null, false);
                        }
                    } else {
                        btn.prop('disabled', false).html('Add Stock');
                        $('#qs_form_errors').removeClass('d-none').html("An unexpected error occurred.");
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('Add Stock');
                    let errors = '<ul class="mb-0">';
                    if(xhr.responseJSON && xhr.responseJSON.errors) {
                        $.each(xhr.responseJSON.errors, function(k, v) {
                            errors += `<li>${v[0]}</li>`;
                        });
                    } else {
                        errors += `<li>Failed to submit. Status: ${xhr.status}</li>`;
                    }
                    errors += '</ul>';
                    $('#qs_form_errors').removeClass('d-none').html(errors);
                }
            });
        });
	});
</script>
