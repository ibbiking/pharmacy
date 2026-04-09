@extends('admin.layouts.app')

@push('page-css')
<style>
    .setup-container {
        max-width: 600px;
        margin: 50px auto;
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
</style>
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Welcome to the Pharmacy System</h3>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="setup-container">
            <h4 class="text-center mb-4">Set up your Business</h4>
            <p class="text-center text-muted mb-4">Before you can start using the system, you need to create your first business profile (Store/Tenant).</p>
            
            @if ($errors->any())
                @foreach ($errors->all() as $error)
                    <div class="alert alert-danger">{{ $error }}</div>
                @endforeach
            @endif

            <form action="{{ route('business.setup.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Business Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Enter your business name" required value="{{ old('name') }}">
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="Enter business phone number" value="{{ old('phone') }}">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" placeholder="Enter business address">{{ old('address') }}</textarea>
                </div>
                
                <div class="form-group">
                    <label>Note (Receipt Footer)</label>
                    <textarea name="note" class="form-control" placeholder="Any additional note for receipts">{{ old('note') }}</textarea>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Create Business</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
