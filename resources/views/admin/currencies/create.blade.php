@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Add Currency</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="{{route('currencies.index')}}">Currencies</a></li>
        <li class="breadcrumb-item active">Add Currency</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <form method="post" action="{{ route('currencies.store') }}">
            @csrf

            <div class="card mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
                <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #0056b3, #007bff);">
                    <h5 class="card-title text-white mb-1"><i class="fas fa-coins mr-2"></i> Currency Details</h5>
                    <small class="text-white-50 font-weight-normal d-block">Adding a currency here makes it available only to your business.</small>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-lg-3">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Code <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="currency_code" maxlength="10" placeholder="e.g. PKR" value="{{ old('currency_code') }}" required style="text-transform: uppercase;">
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Name <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="name" placeholder="e.g. Pakistan Rupee" value="{{ old('name') }}" required>
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Symbol <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" name="symbol" placeholder="e.g. ₨" value="{{ old('symbol') }}" required>
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Exchange Rate</label>
                                <input class="form-control" type="number" step="0.000001" min="0" name="exchange_rate" value="{{ old('exchange_rate', 1) }}" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{route('currencies.index')}}" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                    <button class="btn btn-success btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-check-circle mr-1"></i> Submit Currency</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
