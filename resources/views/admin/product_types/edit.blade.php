@extends('admin.layouts.app')

@push('page-css')
<style>
/* Product Type Name Autocomplete */
.autocomplete-item.autocomplete-active,
.autocomplete-item:hover {
    background-color: #f8f9fa;
    color: #007bff;
}
</style>
@endpush

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Edit Product Type</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Edit Product Type</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <form method="POST" action="{{ route('product-types.update', $productType->id) }}">
            @csrf
            @method('PUT')

            <div class="card mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
                <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #0056b3, #007bff);">
                    <h5 class="card-title text-white mb-1"><i class="fas fa-tags mr-2"></i> Product Category & Dosage Type</h5>
                    <small class="text-white-50 font-weight-normal d-block">Update product dosage form and classification parameters.</small>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Name <span class="text-danger">*</span></label>
                                <div class="custom-autocomplete-wrapper position-relative">
                                    <input class="form-control" type="text" name="name" id="product_type_name_input" autocomplete="off" placeholder="Search or type product type name..." required value="{{ old('name', $productType->name) }}">
                                    <div id="product_type_autocomplete_dropdown" class="w-100 position-absolute shadow bg-white" style="display: none; max-height: 200px; overflow-y: auto; z-index: 1000; border-radius: 10px; top: 100%; left: 0; margin-top: 5px; border: 1px solid #ced4da;">
                                        <ul class="list-unstyled mb-0" id="product_type_autocomplete_list"></ul>
                                        <div id="autocomplete_loading" class="text-center p-2 text-muted" style="display: none;">
                                            <div class="spinner-border spinner-border-sm" role="status"></div> Loading...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Description</label>
                                <textarea class="form-control" name="description" rows="4" style="resize: vertical;" placeholder="Enter description...">{{ old('description', $productType->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions Bar -->
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{route('product-types.index')}}" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                    <button class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-sync-alt mr-1"></i> Update Product Type</button>
                </div>
            </div>
        </form>
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

    const $input = $('#product_type_name_input');
    const $dropdown = $('#product_type_autocomplete_dropdown');
    const $list = $('#product_type_autocomplete_list');
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
            url: "{{ route('product-types.autocomplete', [], false) }}",
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
});
</script>
@endpush