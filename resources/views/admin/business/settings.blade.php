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

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var radios = document.querySelectorAll('.invoice-source-radio');
        var section = document.getElementById('fbrFieldsSection');

        function sync() {
            var checked = document.querySelector('.invoice-source-radio:checked');
            section.style.display = (checked && checked.value === 'fbr') ? 'block' : 'none';
        }

        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    });
</script>
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
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Currency</label>
                                <select name="currency_id" class="form-control select2">
                                    <option value="">-- No currency selected (falls back to app default) --</option>
                                    @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}" {{ old('currency_id', $business->currency_id) == $currency->id ? 'selected' : '' }}>
                                        {{ $currency->currency_code }} &mdash; {{ $currency->name }} ({{ $currency->symbol }}){{ $currency->isGlobal() ? '' : ' [Your Business]' }}
                                    </option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">
                                    Used everywhere a price already shows a currency symbol (dashboard, products, purchases, sales).
                                    <a href="{{ route('currencies.create') }}">Add your own currency</a> if you don't see it here.
                                </small>
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

            <!-- Section 3: Invoice Numbering -->
            <div class="card mb-4 border-warning shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header bg-warning py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-receipt mr-2"></i> Invoice Numbering</h5>
                    @if($business->invoice_source === 'fbr')
                        @if($business->hasFbrCredentials())
                            <span class="badge badge-success px-3 py-2">FBR Linked</span>
                        @else
                            <span class="badge badge-danger px-3 py-2">FBR Selected — Not Linked Yet</span>
                        @endif
                    @endif
                </div>
                <div class="card-body p-4">
                    <p class="text-muted mb-3">Choose how invoice and return numbers are generated for this business.</p>

                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <div class="card h-100" style="border: 2px solid {{ $business->invoice_source === 'local' ? '#3490dc' : '#e3e8ee' }}; border-radius: 10px; cursor: pointer;" onclick="document.getElementById('invoice_source_local').click();">
                                <div class="card-body">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="invoice_source_local" name="invoice_source" value="local" class="custom-control-input invoice-source-radio" {{ old('invoice_source', $business->invoice_source) === 'local' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold" for="invoice_source_local">Local Invoice</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Short, sequential numbers generated by this system (e.g. INV-000123). No external reporting.</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100" style="border: 2px solid {{ $business->invoice_source === 'fbr' ? '#3490dc' : '#e3e8ee' }}; border-radius: 10px; cursor: pointer;" onclick="document.getElementById('invoice_source_fbr').click();">
                                <div class="card-body">
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="invoice_source_fbr" name="invoice_source" value="fbr" class="custom-control-input invoice-source-radio" {{ old('invoice_source', $business->invoice_source) === 'fbr' ? 'checked' : '' }}>
                                        <label class="custom-control-label font-weight-bold" for="invoice_source_fbr">FBR Invoice</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">Invoice/return numbers are issued by FBR for every sale. Requires linking your FBR business details below.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="fbrFieldsSection" style="display: {{ old('invoice_source', $business->invoice_source) === 'fbr' ? 'block' : 'none' }};">
                        <hr>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-1"></i>
                            FBR integration is still being finalized against FBR's live API for this deployment. Save your details here now — the actual "generate real FBR number" call will start working once that's confirmed.
                        </div>
                        <div class="row mb-3">
                            <div class="col-lg-6">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">FBR-Registered Business Name</label>
                                    <input type="text" name="fbr_business_name" class="form-control" placeholder="Name exactly as registered with FBR" value="{{ old('fbr_business_name', $business->fbr_business_name) }}">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">Environment</label>
                                    <select name="fbr_environment" class="form-control">
                                        <option value="sandbox" {{ old('fbr_environment', $business->fbr_environment) === 'sandbox' ? 'selected' : '' }}>Sandbox (testing)</option>
                                        <option value="production" {{ old('fbr_environment', $business->fbr_environment) === 'production' ? 'selected' : '' }}>Production (live)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-lg-4">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">NTN</label>
                                    <input type="text" name="fbr_ntn" class="form-control" placeholder="e.g. 1234567-8" value="{{ old('fbr_ntn', $business->fbr_ntn) }}">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">STRN (Sales Tax Reg. No.)</label>
                                    <input type="text" name="fbr_strn" class="form-control" placeholder="e.g. 12-34-5678-901-23" value="{{ old('fbr_strn', $business->fbr_strn) }}">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">POS Registration No.</label>
                                    <input type="text" name="fbr_pos_registration_no" class="form-control" placeholder="Issued by FBR/PRAL" value="{{ old('fbr_pos_registration_no', $business->fbr_pos_registration_no) }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group mb-0">
                                    <label class="font-weight-bold">FBR API Token</label>
                                    <input type="password" name="fbr_api_token" class="form-control" autocomplete="new-password" placeholder="{{ $business->fbr_api_token ? '•••••••• (saved — leave blank to keep)' : 'Paste the token issued by FBR/PRAL' }}">
                                    <small class="form-text text-muted">Stored encrypted. Leave blank to keep the current token.</small>
                                </div>
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
