@extends('admin.layouts.app')

@push('page-css')
<style>
/* Modern Card Styling */
.card {
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    border: none;
}
.card-body {
    padding: 2rem;
}

/* Form Controls Customization */
.form-control, .service-desc {
    border-radius: 20px !important;
    padding: 10px 15px;
    transition: all 0.3s ease;
    border: 1px solid #ced4da;
    box-shadow: none;
    font-size: 0.95rem;
}

textarea.service-desc {
    border-radius: 15px !important;
}

.form-control:focus, .service-desc:focus {
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.2) !important;
    border-color: #80bdff;
}

/* Labels */
form label {
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 8px;
    font-size: 0.95rem;
    letter-spacing: 0.2px;
}

/* Select2 Customization */
.select2-container .select2-selection--single,
.select2-container .select2-selection--multiple {
    border-radius: 20px !important;
    min-height: 44px;
    border: 1px solid #ced4da !important;
    transition: all 0.3s ease;
    background-color: #fff;
}

.select2-container--default.select2-container--focus .select2-selection--multiple,
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--multiple {
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.2) !important;
    border-color: #80bdff !important;
}

.select2-dropdown {
    border-radius: 20px !important;
    border: 1px solid #ced4da !important;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
    overflow: hidden;
    margin-top: 5px;
}

.select2-search--dropdown .select2-search__field {
    border-radius: 15px !important;
    padding: 8px 15px !important;
    border: 1px solid #ced4da !important;
}

.select2-search--dropdown .select2-search__field:focus {
    box-shadow: 0 0 10px rgba(0,123,255,0.2) !important;
    border-color: #80bdff !important;
    outline: none;
}

.select2-container .select2-selection--single .select2-selection__rendered {
    padding-left: 15px;
    padding-right: 15px;
    line-height: 42px;
    color: #495057;
}

.select2-container .select2-selection--single .select2-selection__arrow {
    height: 42px;
    right: 10px;
}

.select2-container .select2-search--inline .select2-search__field {
    margin-top: 8px;
    padding-left: 10px;
    font-family: inherit;
}

.select2-selection__choice {
    border-radius: 15px !important;
    padding: 3px 10px !important;
    background-color: #e2e8f0 !important;
    border: none !important;
    color: #2d3748 !important;
    margin-top: 6px !important;
    display: inline-flex !important;
    align-items: center;
}

.select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
    border-right: none !important;
    color: #718096 !important;
    margin-right: 6px !important;
    padding: 0 !important;
    position: relative;
}
.select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
    background: transparent !important;
    color: #e53e3e !important;
}

/* Checkbox */
.form-check-input {
    width: 1.25rem;
    height: 1.25rem;
    margin-top: 0.1rem;
    cursor: pointer;
}
.form-check-label {
    padding-left: 0.5rem;
    font-weight: 500;
    cursor: pointer;
}

/* Button */
.submit-btn {
    border-radius: 25px;
    padding: 8px 25px;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    width: auto !important;
    min-width: 150px !important;
}
.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
}

/* Product Name Autocomplete */
.autocomplete-item.autocomplete-active,
.autocomplete-item:hover {
    background-color: #f8f9fa;
    color: #007bff;
}
</style>
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Product</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Edit Product</li>
	</ul>
</div>
@endpush
<!-- Visit codeastro.com for more projects -->
@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">


				<!-- Edit Product -->
				<form method="post" enctype="multipart/form-data" id="update_service"
					action="{{route('products.update',$product)}}">
					@csrf
					@method("PUT")

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-12">
								<div class="form-group">
									<label>Product Name<span class="text-danger">*</span></label>
                                    <div class="custom-autocomplete-wrapper position-relative">
                                        <input type="text" class="form-control" name="product_name" id="product_name_input" autocomplete="off" placeholder="Search or type medicine name..." required value="{{ old('product_name', $product->product_name) }}">
                                        <div id="product_autocomplete_dropdown" class="w-100 position-absolute shadow bg-white" style="display: none; max-height: 200px; overflow-y: auto; z-index: 1000; border-radius: 10px; top: 100%; left: 0; margin-top: 5px; border: 1px solid #ced4da;">
                                            <ul class="list-unstyled mb-0" id="product_autocomplete_list"></ul>
                                            <div id="autocomplete_loading" class="text-center p-2 text-muted" style="display: none;">
                                                <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
                                            </div>
                                        </div>
                                    </div>
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Company <span class="text-danger">*</span></label>
								@php
									$selectedCompany = old('company_id', $product->company_id);
								@endphp
								<select name="company_id" class="select2-tags-single form-control" data-placeholder="Enter new company" required>
									<option value=""></option>
									@if($selectedCompany && !$companies->contains('id', $selectedCompany))
										<option value="{{ $selectedCompany }}" selected>{{ $selectedCompany }}</option>
									@endif
									@foreach($companies as $company)
									<option value="{{ $company->id }}" {{ $selectedCompany == $company->id ? 'selected' : '' }}>
										{{ $company->name }}
									</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label>Formula</label>
								@php
									$selectedFarmulas = old('farmula_id', $product->farmula_id ? explode(',', $product->farmula_id) : []);
								@endphp
								<select name="farmula_id[]" class="select2-tags form-control" multiple="multiple">
									@foreach($selectedFarmulas as $id)
										@php
											$farmula = $farmulas->firstWhere('id', $id);
										@endphp
										@if($farmula)
											<option value="{{ $farmula->id }}" selected>
												{{ $farmula->name }}
											</option>
										@else
											<option value="{{ $id }}" selected>
												{{ $id }}
											</option>
										@endif
									@endforeach
									@foreach($farmulas as $farmula)
										@if(!in_array($farmula->id, $selectedFarmulas))
											<option value="{{ $farmula->id }}">
												{{ $farmula->name }}
											</option>
										@endif
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Product Type <span class="text-danger">*</span></label>
								@php
									$selectedProductType = old('product_type_id', $product->product_type_id);
								@endphp
								<select name="product_type_id" class="select2-tags-single form-control" data-placeholder="Enter new product type" required>
									<option value=""></option>
									@if($selectedProductType && !$productTypes->contains('id', $selectedProductType))
										<option value="{{ $selectedProductType }}" selected>{{ $selectedProductType }}</option>
									@endif
									@foreach($productTypes as $type)
									<option value="{{ $type->id }}" {{ $selectedProductType == $type->id ? 'selected' : '' }}>
										{{ $type->name }}
									</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label>Strength</label>
								@php
									$selectedStrengths = old('strength_id', $product->strength_id ? explode(',', $product->strength_id) : []);
								@endphp
								<select name="strength_id[]" class="select2-tags form-control" multiple="multiple">
									@foreach($selectedStrengths as $id)
										@php
											$strength = $strengths->firstWhere('id', $id);
										@endphp
										@if($strength)
											<option value="{{ $strength->id }}" selected>
												{{ $strength->name }}
											</option>
										@else
											<option value="{{ $id }}" selected>
												{{ $id }}
											</option>
										@endif
									@endforeach
									@foreach($strengths as $strength)
										@if(!in_array($strength->id, $selectedStrengths))
											<option value="{{ $strength->id }}">
												{{ $strength->name }}
											</option>
										@endif
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-3">
							<div class="form-group">
								<label>Rack / Location</label>
								<input type="text" name="rack" class="form-control" value="{{ $product->rack }}">
							</div>
						</div>
						<div class="col-lg-3">
							<div class="form-group">
								<label>Barcode</label>
								<input type="text" name="barcode" class="form-control" value="{{ $product->barcode }}">
							</div>
						</div>

						<div class="col-lg-3">
							<div class="form-group">
								<label>Discount</label>
								<input type="number" step="0.01" name="discount" class="form-control"
									value="{{ $product->discount }}">
							</div>
						</div>

						<div class="col-lg-3">
							<div class="form-group">
								<label>Discount (%)</label>
								<input type="number" step="0.01" name="discount_percent" class="form-control"
									value="{{ $product->discount_percent }}">
							</div>
						</div>

						<div class="col-lg-3">
							<div class="form-check" style="margin-top: 32px;">
								<input type="checkbox" class="form-check-input" name="lock_max_discount" value="1"
									id="lockMaxDiscount" {{ $product->lock_max_discount ? 'checked' : '' }}>
								<label class="form-check-label" for="lockMaxDiscount">Lock Max Discount</label>
							</div>
						</div>
					</div>

					<div class="row mb-4">
						<div class="col-lg-12">
							<div class="form-group">
								<label>Descriptions <span class="text-danger">*</span></label>
								<textarea class="form-control" rows="5" style="resize: vertical;"
									name="description">{{$product->description}}</textarea>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-12 text-right">
							<button class="btn btn-success submit-btn" type="submit" name="form_submit"
								value="submit">Submit</button>
						</div>
					</div>
				</form>
				<!-- /Edit Product -->
			</div>
		</div>
	</div>
</div>
@endsection


@push('page-js')
<script>
$(document).ready(function() {
    let autocompletePage = 1;
    let autocompleteHasMore = true;
    let autocompleteLoading = false;
    let autocompleteTimer;
    let currentFocus = -1;
    let isSelecting = false;

    const $input = $('#product_name_input');
    const $dropdown = $('#product_autocomplete_dropdown');
    const $list = $('#product_autocomplete_list');
    const $loader = $('#autocomplete_loading');

    function fetchAutocomplete(term, page, append = false) {
        if(autocompleteLoading) return;
        autocompleteLoading = true;
        if(!append) {
            $list.empty();
            $dropdown.show();
            currentFocus = -1;
        }
        $loader.show();

        $.ajax({
            url: "{{ route('products.autocomplete') }}",
            data: { term: term, page: page },
            success: function(res) {
                if(res.results.length === 0 && !append) {
                    $dropdown.hide();
                } else {
                    res.results.forEach(function(item) {
                        let safeText = $('<div>').text(item.text).html();
                        $list.append(`<li class="autocomplete-item p-2 border-bottom" style="cursor:pointer;" data-val="${safeText}">${safeText}</li>`);
                    });
                }
                autocompleteHasMore = res.pagination.more;
                autocompleteLoading = false;
                $loader.hide();
            },
            error: function() {
                autocompleteLoading = false;
                $loader.hide();
            }
        });
    }

    $input.on('input', function() {
        clearTimeout(autocompleteTimer);
        let term = $(this).val();
        autocompletePage = 1;
        autocompleteHasMore = true;
        
        if(term.length < 1) {
            $dropdown.hide();
            return;
        }
        
        autocompleteTimer = setTimeout(() => {
            fetchAutocomplete(term, autocompletePage, false);
        }, 300);
    });

    $input.on('focus', function() {
        if (isSelecting) return;
        let term = $(this).val();
        if(term.length >= 1) {
            $dropdown.show();
        }
    });

    function addActive(items) {
        if (!items || !items.length) return false;
        items.removeClass('autocomplete-active text-primary bg-light');
        if (currentFocus >= items.length) currentFocus = 0;
        if (currentFocus < 0) currentFocus = (items.length - 1);
        
        let activeEl = $(items[currentFocus]);
        activeEl.addClass('autocomplete-active text-primary bg-light');

        let itemTop = activeEl.position().top;
        let itemHeight = activeEl.outerHeight();
        let listHeight = $dropdown.height();
        let scrollPos = $dropdown.scrollTop();

        if (itemTop < 0) {
            $dropdown.scrollTop(scrollPos + itemTop);
        } else if (itemTop + itemHeight > listHeight) {
            $dropdown.scrollTop(scrollPos + itemTop + itemHeight - listHeight);
        }
    }

    $input.on('keydown', function(e) {
        let items = $list.find('.autocomplete-item');
        if (!$dropdown.is(':visible') || !items.length) return;

        if (e.keyCode === 40) { // Down
            e.preventDefault();
            currentFocus++;
            addActive(items);
        } else if (e.keyCode === 38) { // Up
            e.preventDefault();
            currentFocus--;
            addActive(items);
        } else if (e.keyCode === 13) { // Enter
            e.preventDefault();
            if (currentFocus > -1) {
                $(items[currentFocus]).click();
            }
        } else if (e.keyCode === 27) { // Escape
            $dropdown.hide();
        }
    });

    $dropdown.on('scroll', function() {
        if($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight - 5) {
            if(autocompleteHasMore && !autocompleteLoading) {
                autocompletePage++;
                fetchAutocomplete($input.val(), autocompletePage, true);
            }
        }
    });

    $(document).on('click', '.autocomplete-item', function() {
        isSelecting = true;
        $input.val($(this).data('val'));
        $dropdown.hide();
        $input.focus(); 
        setTimeout(() => { isSelecting = false; }, 100);
    });

    $(document).on('click', function(e) {
        if(!$(e.target).closest('.custom-autocomplete-wrapper').length) {
            $dropdown.hide();
        }
    });

    $('.select2-tags').select2({
        tags: true,
        width: '100%',
        tokenSeparators: [',']
    });

    $('.select2-tags-single').each(function() {
        $(this).select2({
            tags: true,
            width: '100%',
            tokenSeparators: [','],
            placeholder: $(this).attr('data-placeholder')
        });
    });

    $('.select2-tags-single').on('select2:open', function() {
        let placeholder = $(this).attr('data-placeholder');
        if (placeholder) {
            setTimeout(function() {
                $('.select2-search__field').attr('placeholder', placeholder).css('padding-left', '10px');
            }, 10);
        }
    });

    // Preserve selection sequence for Farmulas and Strengths
    $('select[name="farmula_id[]"], select[name="strength_id[]"]').on('select2:select', function (e) {
        $(this).append($(e.params.data.element));
        $(this).trigger('change');
    });
});
</script>
@endpush