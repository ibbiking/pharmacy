@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Global Sale Price Preferences</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Global Sale Price Preferences</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">

                <form method="post" action="{{ route('global-sale-price-preferences.store') }}">
                    @csrf

                    @php
                        // radio set for default preference
                        $radioSlugs = ['static-price','stock-wise-price','previous-inventory-price'];
                        $radioPrefs = $preferences->whereIn('slug', $radioSlugs);
                        $hasSelectedRadio = (bool) $radioPrefs->firstWhere('status', 1);
                        // tax checkbox preference
                        $taxPref = $preferences->where('slug', 'sale-price-including-tax')->first();
                    @endphp

                    <div class="form-group">
                        <label><strong>Select Default Sale Price Preference</strong></label>
                        @foreach($radioPrefs as $pref)
                            <div class="form-check">
                                <input class="form-check-input sale-pref-radio"
                                       type="radio"
                                       name="sale_price_preference_id"
                                       id="sale_pref_{{ $pref->id }}"
                                       value="{{ $pref->id }}"
                                       {{ $pref->status ? 'checked' : '' }}>
                                <label class="form-check-label" for="sale_pref_{{ $pref->id }}">{{ $pref->preference }}</label>
                            </div>
                        @endforeach

                        <!-- Clear button; disabled initially if no radio selected -->
                        <button type="button"
                                id="clear-selection"
                                class="btn btn-warning btn-sm mt-2"
                                @if(!$hasSelectedRadio) disabled @endif>
                            Clear Selection
                        </button>
                    </div>

                    <div class="form-group mt-3">
                        <div class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   id="sale_price_including_tax"
                                   name="sale_price_including_tax"
                                   value="1"
                                   {{ $taxPref && $taxPref->status ? 'checked' : '' }}>
                            <label class="form-check-label" for="sale_price_including_tax"><strong>Sale Price Including Tax</strong></label>
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

    // initial state (in case DOM had pre-selected radio)
    updateClearButtonState();

    // update button when radios change
    radios.forEach(radio => {
        radio.addEventListener('change', updateClearButtonState);
    });

    // clear selection behaviour
    clearBtn.addEventListener('click', function () {
        radios.forEach(r => r.checked = false);
        // update button state immediately
        updateClearButtonState();

        // optional: if you want to focus first radio after clearing:
        // if (radios[0]) radios[0].focus();
    });
});
</script>
@endpush