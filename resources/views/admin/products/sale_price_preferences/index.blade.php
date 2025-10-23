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
                    $hasSelectedRadio = (bool) $radioPrefs->firstWhere('id', $product->sale_price_preference_id);
                    @endphp

                    <!-- Radio buttons for sale price preference -->
                    <div class="form-group">
                        <label><strong>Select Sale Price Preference</strong></label>
                        @foreach($radioPrefs as $pref)
                        <div class="form-check">
                            <input class="form-check-input sale-pref-radio" type="radio" name="sale_price_preference_id"
                                id="sale_pref_{{ $pref->id }}" value="{{ $pref->id }}" {{
                                $product->sale_price_preference_id == $pref->id ? 'checked' : '' }}>
                            <label class="form-check-label" for="sale_pref_{{ $pref->id }}">{{ $pref->preference
                                }}</label>
                        </div>
                        @endforeach

                        <!-- Clear button for radios -->
                        <button type="button" id="clear-selection" class="btn btn-warning btn-sm mt-2"
                            @if(!$hasSelectedRadio) disabled @endif>
                            Clear Selection
                        </button>
                    </div>

                    <!-- Sale Price Including Tax Checkbox -->
                    <div class="form-group mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sale_price_including_tax"
                                name="sale_price_including_tax" value="1" {{ $product->sale_price_including_tax ?
                            'checked' : '' }}>
                            <label class="form-check-label" for="sale_price_including_tax">
                                <strong>Sale Price Including Tax</strong>
                            </label>
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