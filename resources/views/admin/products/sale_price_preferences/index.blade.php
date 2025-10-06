@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Sale Price Preference for {{ $product->product_name }}</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
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

                    <div class="form-group">
                        <label><strong>Select Sale Price Preference</strong></label>
                        @foreach($preferences as $pref)
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="radio"
                                       name="sale_price_preference_id"
                                       value="{{ $pref->id }}"
                                       {{ $product->sale_price_preference_id == $pref->id ? 'checked' : '' }}
                                >
                                <label class="form-check-label">{{ $pref->preference }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="submit-section">
                        <button class="btn btn-success submit-btn" type="submit">Save</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endsection