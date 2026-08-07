@extends('admin.layouts.app')

@push('page-css')
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Business Settings</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
		<li class="breadcrumb-item active">Business Settings</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-pill px-4" role="alert">
                <i class="fas fa-check-circle mr-2"></i> {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif
        
        @if ($errors->any())
            @foreach ($errors->all() as $error)
                <div class="alert alert-danger alert-dismissible fade show rounded-pill px-4" role="alert">
                    <i class="fas fa-exclamation-circle mr-2"></i> {{ $error }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endforeach
        @endif

        <form action="{{ route('business.settings.update') }}" method="POST">
            @csrf

            <!-- Section 1: Business Profile -->
            <div class="card mb-4 border-primary shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="card-title text-white mb-0"><i class="fas fa-store mr-2"></i> Business Information</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="Enter your business name" required value="{{ old('name', $business->name) }}">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="Enter business phone number" value="{{ old('phone', $business->phone) }}">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Business Address</label>
                                <textarea name="address" class="form-control" rows="3" style="resize: vertical;" placeholder="Enter business address">{{ old('address', $business->address) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Note (Receipt Footer)</label>
                                <textarea name="note" class="form-control" rows="3" style="resize: vertical;" placeholder="Any additional note for receipts">{{ old('note', $business->note) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Global Preferences -->
            <div class="card mb-4 border-info shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-info text-white py-3">
                    <h5 class="card-title text-white mb-0"><i class="fas fa-sliders-h mr-2"></i> Global Preferences</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Global Minimum Stock Indication Quantity</label>
                                <input type="number" step="0.01" min="0" name="global_min_indicated_qty" class="form-control" placeholder="e.g. 50" value="{{ old('global_min_indicated_qty', $globalMinStock ?? '') }}">
                                <small class="form-text text-muted">Applied to all products on their base category if no product-specific minimum quantity is set.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions Bar -->
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{route('dashboard')}}" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                    <button class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-save mr-1"></i> Save Settings</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
