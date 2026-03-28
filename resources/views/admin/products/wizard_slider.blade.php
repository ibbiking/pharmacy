<div class="modal-header bg-primary text-white">
    <h5 class="modal-title">Setup Wizard: {{ $product->product_name }}</h5>
    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="modal-body p-0">
    <div class="row m-0">
        <div class="col-md-3 bg-light p-3 border-right" style="min-height: 500px">
            <h6 class="text-muted text-uppercase font-weight-bold mb-3">Setup Steps</h6>
            <div class="nav flex-column nav-pills" id="wizard-tabs" role="tablist" aria-orientation="vertical">
                <a class="nav-link active font-weight-bold" id="tab-categories-link" data-toggle="pill" href="#tab-categories" role="tab" aria-controls="tab-categories" aria-selected="true">
                    1. Assign Category
                </a>
                <a class="nav-link font-weight-bold {{ $productCategory ? '' : 'disabled' }}" id="tab-parameters-link" data-toggle="pill" href="#tab-parameters" role="tab" aria-controls="tab-parameters" aria-selected="false">
                    2. Packaging & Pricing
                </a>
                <a class="nav-link font-weight-bold {{ !$product->is_draft ? '' : 'disabled' }}" id="tab-preferences-link" data-toggle="pill" href="#tab-preferences" role="tab" aria-controls="tab-preferences" aria-selected="false">
                    3. Sale Preference <small>(Optional)</small>
                </a>
            </div>
            
            <div class="mt-4 p-2 {{ $product->is_draft ? 'bg-warning' : 'bg-success' }} text-white text-center rounded">
                <strong>Status:</strong> <br>
                {{ $product->is_draft ? 'Draft (Incomplete)' : 'Real Product (Live)' }}
            </div>
        </div>

        <div class="col-md-9 p-4">
            <style>
                /* Completely hide disabled options from the select2 dropdown */
                .select2-container--default .select2-results__option[aria-disabled="true"] {
                    display: none;
                }
            </style>
            <div class="tab-content" id="wizard-tabContent">
                
                <!-- STEP 1: CATEGORY RELATION -->
                <div class="tab-pane fade show active" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories-link">
                    <h4 class="mb-4"><i class="fas fa-sitemap text-primary mr-2"></i> Product Category Relation</h4>

                    <form id="wizard-cat-form" action="{{ route('product-categories.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">

                        <div class="form-group row">
                            <label class="col-lg-3 col-form-label">Parent Category</label>
                            <div class="col-lg-9">
                                <select class="form-control select2" id="parent" name="parent_category_id" {{ $lastChildId ? 'disabled' : '' }} required>
                                    <option value="">-- Select Parent --</option>
                                    @foreach($parentCategories as $category)
                                        <option value="{{ $category->id }}" {{ $lastChildId == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($lastChildId)
                                    <input type="hidden" name="parent_category_id" value="{{ $lastChildId }}">
                                @endif
                            </div>
                        </div>

                        <div class="form-group row" id="child-category-wrapper">
                            <label class="col-lg-3 col-form-label">Child Category</label>
                            <div class="col-lg-9">
                                <select class="form-control select2" id="child" name="child_category_id" {{ $lastChildId ? '' : 'disabled' }} required>
                                    <option value="">-- Select Child --</option>
                                    @foreach($childCategories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" id="single-packaging-wrapper" {!! $lastChildId ? 'style="display:none;"' : '' !!}>
                            <div class="col-lg-3"></div>
                            <div class="col-lg-9">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="singlePackagingCheck" name="single_packaging" value="1">
                                    <label class="form-check-label font-weight-bold text-info" for="singlePackagingCheck">Product has only ONE level of packaging</label>
                                </div>
                            </div>
                        </div>

                        <div class="text-right mt-3">
                            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-plus"></i> Add Relation</button>
                        </div>
                    </form>

                    @if($relations->count())
                        <div class="mt-5 border-top pt-4">
                            <h5 class="mb-3">Existing Relations</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped text-sm">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Parent Category</th>
                                            <th>Child Category</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($relations as $index => $relation)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $relation->parentCategory->name ?? '-' }}</td>
                                            <td>{{ $relation->childCategory->name ?? '-' }}</td>
                                            <td>
                                                <button type="button" data-route="{{ route('product-categories.destroy', $relation->id) }}" class="btn btn-danger btn-sm wizard-cat-deletebtn"><i class="fas fa-trash"></i></button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-right mt-4">
                                <button type="button" class="btn btn-success btn-lg px-5" onclick="$('#tab-parameters-link').removeClass('disabled').tab('show');">Confirm & Proceed <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- STEP 2: PARAMETERS -->
                <div class="tab-pane fade" id="tab-parameters" role="tabpanel" aria-labelledby="tab-parameters-link">
                    <h4 class="mb-4"><i class="fas fa-box-open text-warning mr-2"></i> Packaging, Quantity & Pricing Details</h4>

                    @if(!$productCategory)
                        <div class="alert alert-danger">Please map a Category Relation first.</div>
                    @else
                        <form id="wizard-param-form" action="{{ route('products.parameters.store', $product->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="category_id" value="{{ $productCategory->child_category_id }}">

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width: 22%;">Packaging Level</th>
                                            <th style="width: 28%;">Quantity Config</th>
                                            <th style="width: 25%;">Purchase Price <br><small><i>(For this level)</i></small></th>
                                            <th style="width: 25%;">Sale Price <br><small><i>(For this level)</i></small></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($baseCategory)
                                            @php
                                                $baseParam = $parameters ? $parameters->get($baseCategory->id) : null;
                                            @endphp
                                            <tr class="bg-light param-row" data-index="base">
                                                <td class="font-weight-bold">{{ $baseCategory->name }} <br><span class="badge badge-primary mt-1">Top-level Packaging</span></td>
                                                <td>
                                                    <input type="hidden" name="parameters[base][parent_category_id]" value="{{ $baseCategory->id }}">
                                                    <input type="hidden" name="parameters[base][child_category_id]" value="{{ $baseCategory->id }}">
                                                    <input type="hidden" name="parameters[base][category_id]" value="{{ $baseCategory->id }}">
                                                    <input type="number" name="parameters[base][quantity]" value="1" class="form-control font-weight-bold param-qty" readonly title="Base quantity is always 1">
                                                    <small class="form-text text-muted">Fixed top wrapper quantity</small>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="parameters[base][static_category_unit_purchase_price]" 
                                                           value="{{ old('parameters.base.static_category_unit_purchase_price', ($baseParam && $baseParam->static_category_unit_purchase_price) ? round($baseParam->static_category_unit_purchase_price, 2) : '') }}" 
                                                           class="form-control param-pp" placeholder="0.00" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="parameters[base][static_category_unit_sale_price]" 
                                                           value="{{ old('parameters.base.static_category_unit_sale_price', ($baseParam && $baseParam->static_category_unit_sale_price) ? round($baseParam->static_category_unit_sale_price, 2) : '') }}" 
                                                           class="form-control param-sp" placeholder="0.00" required>
                                                </td>
                                            </tr>
                                        @endif
                                        @foreach($children as $idx => $child)
                                            @php
                                                $param = $parameters ? $parameters->get($child->id) : null;
                                            @endphp
                                            <tr class="param-row" data-index="{{$idx}}">
                                                <td class="font-weight-bold">{{ $child->name }}</td>
                                                <td>
                                                    <input type="hidden" name="parameters[{{$idx}}][parent_category_id]" value="{{ $child->parent_id }}">
                                                    <input type="hidden" name="parameters[{{$idx}}][child_category_id]" value="{{ $child->id }}">
                                                    <input type="hidden" name="parameters[{{$idx}}][category_id]" value="{{ $baseCategory->id ?? '' }}">
                                                    <input type="number" step="0.01" name="parameters[{{$idx}}][quantity]" 
                                                           value="{{ old('parameters.'.$idx.'.quantity', $param ? $param->quantity : '') }}" 
                                                           class="form-control param-qty" placeholder="Enter Qty" required>
                                                    <small class="form-text text-muted" style="line-height: 1.2;">Qty of <strong>{{ $child->name }}</strong> per {{ $child->parent->name ?? 'parent' }}</small>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="parameters[{{$idx}}][static_category_unit_purchase_price]" 
                                                           value="{{ old('parameters.'.$idx.'.static_category_unit_purchase_price', ($param && $param->static_category_unit_purchase_price) ? round($param->static_category_unit_purchase_price, 2) : '') }}" 
                                                           class="form-control param-pp" placeholder="0.00" required>
                                                </td>
                                                <td>
                                                    <input type="number" step="0.01" name="parameters[{{$idx}}][static_category_unit_sale_price]" 
                                                           value="{{ old('parameters.'.$idx.'.static_category_unit_sale_price', ($param && $param->static_category_unit_sale_price) ? round($param->static_category_unit_sale_price, 2) : '') }}" 
                                                           class="form-control param-sp" placeholder="0.00" required>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="text-right mt-4">
                                @if($product->is_draft)
                                    <button type="submit" class="btn btn-success btn-lg px-4"><i class="fas fa-check-circle"></i> Save & Promote to Real Product</button>
                                @else
                                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Updates <i class="fas fa-arrow-right"></i></button>
                                @endif
                            </div>
                        </form>
                    @endif
                </div>

                <!-- STEP 3: PREFERENCES -->
                <div class="tab-pane fade" id="tab-preferences" role="tabpanel" aria-labelledby="tab-preferences-link">
                    <h4 class="mb-4">Sale Price Preference</h4>

                    <form id="wizard-pref-form" action="{{ route('products.sale-price-preferences.store', $product->id) }}" method="POST">
                        @csrf
                        @php
                            $radioSlugs = ['static-price', 'stock-wise-price', 'previous-inventory-price'];
                            $radioPrefs = $availablePreferences->whereIn('slug', $radioSlugs);
                            $hasSelectedRadio = false;
                        @endphp
                        <div class="form-group mb-4">
                            <label class="font-weight-bold d-block pb-2">Select Primary Parsing Logic</label>
                            @foreach($radioPrefs as $pref)
                                @php
                                    if($product->sale_price_preference_id == $pref->id) {
                                        $hasSelectedRadio = true;
                                    }
                                @endphp
                                <div class="custom-control custom-radio mb-3">
                                    <input type="radio" id="pref_{{ $pref->id }}" name="sale_price_preference_id" value="{{ $pref->id }}" 
                                           class="custom-control-input sale-pref-radio" {{ $product->sale_price_preference_id == $pref->id ? 'checked' : '' }}>
                                    <label class="custom-control-label font-weight-bold w-100" for="pref_{{ $pref->id }}">
                                        @if($pref->slug == 'static-price')
                                            Static Sale Price
                                            <small class="d-block text-muted font-weight-normal mt-1">Forces POS to strictly use the Static Sale Price defined in Setup Tab 2.</small>
                                        @elseif($pref->slug == 'stock-wise-price')
                                            Stock wise Sale Price
                                            <small class="d-block text-muted font-weight-normal mt-1">POS dynamically selects the sale price matching the earliest available live Batch.</small>
                                        @elseif($pref->slug == 'previous-inventory-price')
                                            Last/previous inventory Sale Price
                                            <small class="d-block text-muted font-weight-normal mt-1">POS mimics legacy logic locking to the previously imported system inventory state.</small>
                                        @else
                                            {{ $pref->name }}
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                            <!-- Clear button for radios -->
                            <button type="button" id="clear-selection" class="btn btn-warning btn-sm mt-3" {{ !$hasSelectedRadio ? 'disabled' : '' }}>
                                Clear Selection
                            </button>
                        </div>

                        <div class="form-group mb-4 bg-light p-3 rounded border">
                            <div class="custom-control custom-checkbox text-dark">
                                <input type="checkbox" id="tax_incl" name="sale_price_including_tax" value="1" 
                                       class="custom-control-input" {{ $product->sale_price_including_tax ? 'checked' : '' }}>
                                <label class="custom-control-label font-weight-bold" for="tax_incl">Product Final Sale Price Includes Imposed Taxes natively</label>
                            </div>
                        </div>

                        <div class="text-right mt-4">
                            <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-check"></i> Finalize Setup</button>
                        </div>
                    </form>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize standard plugins
    if($('.select2').length > 0) {
        $('.select2').select2({ width: '100%', dropdownParent: $('#setupWizardModal') });
    }

    // Ajax Form bindings for smooth transitions
    // Intercept Add Form relation mapped
    $('#wizard-cat-form').submit(function(e){
        e.preventDefault();
        let btn = $(this).find('button[type=submit]');
        let original = btn.html();
        btn.html('<i class="fas fa-spin fa-spinner"></i> Saving...').prop('disabled', true);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                Snackbar.show({text: res.message, pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#8dbf42'});
                reloadWizardUI();
            },
            error: function(err) {
                btn.html(original).prop('disabled', false);
                let msg = err.responseJSON ? err.responseJSON.message : 'Validation failed. Please verify categories.';
                Snackbar.show({text: msg, pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#e7515a'});
            }
        });
    });

    // Handle AJAX Deletion of relations
    $('.wizard-cat-deletebtn').click(function(e){
        e.preventDefault();
        let route = $(this).data('route');
        if(!confirm('WARNING: Deleting this category relation will ALSO permanently delete any packaging and pricing parameters you have configured for this level! Are you sure you want to proceed?')) return;

        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: route,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(res) {
                Snackbar.show({text: 'Relation deleted.', pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#8dbf42'});
                reloadWizardUI();
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                Snackbar.show({text: 'Failed to delete relation.', pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#e7515a'});
            }
        });
    });

    // Helper: reload the slider state entirely post-update
    function reloadWizardUI() {
        let fetchUrl = "{{ url('admin/products') }}/{{ $product->id }}/setup-wizard";
        $('#setupWizardContent').html('<div class="modal-body text-center p-5"><div class="spinner-border mt-5 text-primary"></div></div>');
        $.get(fetchUrl, function(html){
            $('#setupWizardContent').html(html);
        });
    }

    // Dynamic Select Filtering logically identical to create.blade.php
    var parentDrop = $('#parent');
    var childDrop  = $('#child');
    @if($lastChildId)
        if(childDrop.length > 0) {
            childDrop.prop('disabled', false);
            childDrop.find('option').each(function(){
                if($(this).val() == '{{ $lastChildId }}' && $(this).val() !== "") {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
            childDrop.select2({ width: '100%', dropdownParent: $('#setupWizardModal') });
        }
    @else
        if(parentDrop.length > 0) {
            parentDrop.on('change', function(){
                let pval = $(this).val();
                childDrop.prop('disabled', !pval);

                if(childDrop.val() == pval && pval !== "") {
                    childDrop.val('').trigger('change');
                }

                childDrop.find('option').each(function(){
                    if($(this).val() == pval && $(this).val() !== "") {
                        $(this).prop('disabled', true);
                    } else {
                        $(this).prop('disabled', false);
                    }
                });
                childDrop.select2({ width: '100%', dropdownParent: $('#setupWizardModal') });
            });
            // trigger change on load to hide initially selected parent
            if (parentDrop.val()) {
                parentDrop.trigger('change');
            }
        }
    @endif

    $('#singlePackagingCheck').change(function() {
        if($(this).is(':checked')) {
            $('#child-category-wrapper').hide();
            $('#child').prop('required', false);
        } else {
            $('#child-category-wrapper').show();
            $('#child').prop('required', true);
        }
    });

    $('#wizard-param-form').submit(function(e){
        e.preventDefault();
        let btn = $(this).find('button[type=submit]');
        let original = btn.html();
        btn.html('<i class="fas fa-spin fa-spinner"></i> Saving...').prop('disabled', true);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                btn.html(original).prop('disabled', false);
                Snackbar.show({text: res.message, pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#8dbf42'});
                
                if(res.promoted) {
                    $('#tab-preferences-link').removeClass('disabled').tab('show');
                    
                    // Fire a custom event letting index/draft datatable redraw if it exists
                    if($('#product-table').length > 0) {
                        $('#product-table').DataTable().ajax.reload();
                    }
                } else {
                    $('#tab-preferences-link').removeClass('disabled').tab('show');
                }
            },
            error: function(err) {
                btn.html(original).prop('disabled', false);
                Snackbar.show({text: 'Failed to save parameters. Check limits.', pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#e7515a'});
            }
        });
    });

    var radios = $('.sale-pref-radio');
    var clearBtn = $('#clear-selection');
    
    radios.on('change', function() {
        clearBtn.prop('disabled', false);
    });
    
    clearBtn.on('click', function() {
        radios.prop('checked', false);
        clearBtn.prop('disabled', true);
    });

    $('#wizard-pref-form').submit(function(e){
        e.preventDefault();
        let btn = $(this).find('button[type=submit]');
        let original = btn.html();
        btn.html('<i class="fas fa-spin fa-spinner"></i> Finalizing...').prop('disabled', true);
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                btn.html(original).prop('disabled', false);
                Snackbar.show({text: res.message, pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#8dbf42'});
                
                // Done! Close Modal.
                setTimeout(() => {
                    $('#setupWizardModal').modal('hide');
                }, 800);
            },
            error: function(err) {
                btn.html(original).prop('disabled', false);
                Snackbar.show({text: 'Failed to save preferences.', pos: 'top-right', actionTextColor: '#fff', backgroundColor: '#e7515a'});
            }
        });
    });

    // Setup wizard parameter cascading pricing logic
    function evaluateParamRowStates() {
        let rows = $('.param-row');
        let parentValid = true; 
        
        rows.each(function(index) {
            let $row = $(this);
            let qtyInput = $row.find('.param-qty');
            let ppInput = $row.find('.param-pp');
            let spInput = $row.find('.param-sp');
            
            if (index > 0) {
                if (parentValid) {
                    qtyInput.prop('readonly', false);
                    let qty = parseFloat(qtyInput.val());
                    
                    if (!isNaN(qty) && qty > 0) {
                        ppInput.prop('readonly', false);
                        spInput.prop('readonly', false);
                        
                        let pp = parseFloat(ppInput.val());
                        let sp = parseFloat(spInput.val());
                        parentValid = (!isNaN(pp) && pp > 0 && !isNaN(sp) && sp > 0);
                    } else {
                        ppInput.prop('readonly', true);
                        spInput.prop('readonly', true);
                        parentValid = false;
                    }
                } else {
                    qtyInput.prop('readonly', true);
                    ppInput.prop('readonly', true);
                    spInput.prop('readonly', true);
                    parentValid = false;
                }
            } else {
                let pp = parseFloat(ppInput.val());
                let sp = parseFloat(spInput.val());
                parentValid = (!isNaN(pp) && pp > 0 && !isNaN(sp) && sp > 0);
            }
        });
    }

    $('.param-row').each(function(index) {
        let $row = $(this);
        let qtyInput = $row.find('.param-qty');
        let ppInput = $row.find('.param-pp');
        let spInput = $row.find('.param-sp');
        
        qtyInput.on('input', function() {
            if (index > 0) {
                let prevRow = $('.param-row').eq(index - 1);
                let prevPP = parseFloat(prevRow.find('.param-pp').val());
                let prevSP = parseFloat(prevRow.find('.param-sp').val());
                let qty = parseFloat($(this).val());
                
                if (!isNaN(qty) && qty > 0 && !isNaN(prevPP) && !isNaN(prevSP)) {
                    let newPP = (prevPP / qty).toFixed(2);
                    let newSP = (prevSP / qty).toFixed(2);
                    ppInput.val(newPP).trigger('change-auto');
                    spInput.val(newSP).trigger('change-auto');
                }
            }
            evaluateParamRowStates();
        });
        
        ppInput.on('input change-auto', function() {
            let nextRow = $('.param-row').eq(index + 1);
            if (nextRow.length > 0) {
                let nextQty = parseFloat(nextRow.find('.param-qty').val());
                let currentPP = parseFloat($(this).val());
                if (!isNaN(nextQty) && nextQty > 0 && !isNaN(currentPP)) {
                    let nextPP = (currentPP / nextQty).toFixed(2);
                    nextRow.find('.param-pp').val(nextPP).trigger('change-auto');
                }
            }
            evaluateParamRowStates();
        });
        
        spInput.on('input change-auto', function() {
            let nextRow = $('.param-row').eq(index + 1);
            if (nextRow.length > 0) {
                let nextQty = parseFloat(nextRow.find('.param-qty').val());
                let currentSP = parseFloat($(this).val());
                if (!isNaN(nextQty) && nextQty > 0 && !isNaN(currentSP)) {
                    let nextSP = (currentSP / nextQty).toFixed(2);
                    nextRow.find('.param-sp').val(nextSP).trigger('change-auto');
                }
            }
            evaluateParamRowStates();
        });
    });

    // Run initial state evaluation
    evaluateParamRowStates();
</script>
