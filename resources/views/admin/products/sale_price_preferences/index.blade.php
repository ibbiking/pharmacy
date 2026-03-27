@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Sale Price Preference for {{ $product->product_name }}</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active">Sale Price Preference</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">

                <form method="post" action="{{ route('products.sale-price-preferences.store', $product->id) }}">
                    @csrf

                    @php
                    $radioSlugs = ['static-price','stock-wise-price','previous-inventory-price'];
                    $radioPrefs = $preferences->whereIn('slug', $radioSlugs);
                    $hasSelectedRadio = false;
                    @endphp

                    <div class="form-group mb-4 mt-2">
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
                                        {{ $pref->preference }}
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

                    <div class="submit-section mt-3">
                        <button class="btn btn-success submit-btn" type="submit">Save</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
    const radios = Array.from(document.querySelectorAll('.sale-pref-radio'));
    const clearBtn = document.getElementById('clear-selection');

    if (!clearBtn) return;

    function updateClearButtonState() {
        const anyChecked = radios.some(r => r.checked);
        clearBtn.disabled = !anyChecked;
    }

    // initial state
    updateClearButtonState();

    // update button when radios change
    radios.forEach(radio => {
        radio.addEventListener('change', updateClearButtonState);
    });

    // clear selection behavior
    clearBtn.addEventListener('click', function () {
        radios.forEach(r => r.checked = false);
        updateClearButtonState();
    });
});
</script>
@endpush