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
        <div class="card">
            <div class="card-body">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                
                @if ($errors->any())
                    @foreach ($errors->all() as $error)
                        <div class="alert alert-danger">{{ $error }}</div>
                    @endforeach
                @endif

                <form action="{{ route('business.settings.update') }}" method="POST">
                    @csrf
                    <div class="form-group border-bottom pb-4 mb-4">
                        <label>Business Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Enter your business name" required value="{{ old('name', $business->name) }}">
                    </div>
                    
                    <div class="form-group border-bottom pb-4 mb-4">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Enter business phone number" value="{{ old('phone', $business->phone) }}">
                    </div>
                    
                    <div class="form-group border-bottom pb-4 mb-4">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Enter business address">{{ old('address', $business->address) }}</textarea>
                    </div>
                    
                    <div class="form-group border-bottom pb-4 mb-4">
                        <label>Note (Receipt Footer)</label>
                        <textarea name="note" class="form-control" rows="3" placeholder="Any additional note for receipts">{{ old('note', $business->note) }}</textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Update Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
