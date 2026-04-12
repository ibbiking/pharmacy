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
    <h3 class="page-title">Suggest Generic Product</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{route('generic_products.index')}}">All Generics</a></li>
        <li class="breadcrumb-item active">Suggest Product</li>
    </ul>
</div>
@endpush
<!-- Visit codeastro.com for more projects -->

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body custom-edit-service">
                <!-- Add Product -->
                <form method="post" enctype="multipart/form-data" id="update_service"
                    action="{{route('generic_products.suggest.store')}}">
                    @csrf
                    <div class="service-fields mb-3">
                        <div class="row">

                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Medicine Name<span class="text-danger">*</span></label>
                                    <div class="custom-autocomplete-wrapper position-relative">
                                        <input type="text" class="form-control" name="product_name" id="product_name_input" autocomplete="off" placeholder="Search or type medicine name..." required value="{{ old('product_name') }}">
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
                                <label>Company / Brand<span class="text-danger">*</span></label>
                                @php
                                    $oldCompany = old('company_id');
                                @endphp
                                <select name="company_id" class="select2-tags-single form-control" data-placeholder="Select or enter new company" required>
                                    <option value=""></option>
                                    @if($oldCompany && !$companies->contains('id', $oldCompany))
                                        <option value="{{ $oldCompany }}" selected>{{ $oldCompany }}</option>
                                    @endif
                                    @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $oldCompany == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-group">
                                <label>Formula / Salt</label>
                                @php
                                    $oldFarmulas = old('farmula_id', []);
                                @endphp
                                <select name="farmula_id[]" class="select2-tags form-control" multiple="multiple">
                                    @foreach($oldFarmulas as $id)
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
                                        @if(!in_array($farmula->id, $oldFarmulas))
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
                                    $oldProductType = old('product_type_id');
                                @endphp
                                <select name="product_type_id" class="select2-tags-single form-control" data-placeholder="Select or enter new product type" required>
                                    <option value=""></option>
                                    @if($oldProductType && !$productTypes->contains('id', $oldProductType))
                                        <option value="{{ $oldProductType }}" selected>{{ $oldProductType }}</option>
                                    @endif
                                    @foreach($productTypes as $type)
                                    <option value="{{ $type->id }}" {{ $oldProductType == $type->id ? 'selected' : '' }}>
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
                                    $oldStrengths = old('strength_id', []);
                                @endphp
                                <select name="strength_id[]" class="select2-tags form-control" multiple="multiple">
                                    @foreach($oldStrengths as $id)
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
                                        @if(!in_array($strength->id, $oldStrengths))
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
                                <input type="text" name="rack" class="form-control" placeholder="Enter Rack/Location">
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="form-group">
                                <label>Barcode</label>
                                <input type="text" name="barcode" class="form-control" placeholder="Enter Barcode">
                            </div>
                        </div>

                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Discount</label>
                                    <input type="number" step="0.01" name="discount" class="form-control"
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Discount (%)</label>
                                    <input type="number" step="0.01" name="discount_percent" class="form-control"
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-lg-3">
                                <div class="form-check" style="margin-top: 32px;">
                                    <input type="checkbox" class="form-check-input" name="lock_max_discount" value="1"
                                        id="lockMaxDiscount">
                                    <label class="form-check-label" for="lockMaxDiscount">Lock Max Discount</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label>Descriptions</label>
                                    <textarea class="form-control service-desc" name="description" rows="5" style="resize: vertical;">{{old('description')}}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-12 text-right">
                                <button class="btn btn-success submit-btn" type="submit" name="form_submit" value="submit">Submit Suggestion</button>
                            </div>
                        </div>
                </form>
                <!-- /Add Product -->
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

    // We can leave autocomplete attached optionally to generic products autocomplete endpoint later if you open an endpoint. For now, we will leave the UI intact but no endpoint.
    $input.attr("autocomplete", "off");

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
