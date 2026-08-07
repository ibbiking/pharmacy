@extends('admin.layouts.app')

@push('page-css')
    
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Create User</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item active">Dashboard</li>
	</ul>
</div>
@endpush

@section('content')

<div class="row">
    <div class="col-md-12 col-lg-12">
        <form method="POST" enctype="multipart/form-data" action="{{route('users.store')}}">
            @csrf
            
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
                <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #0056b3, #007bff);">
                    <h5 class="card-title text-white mb-1"><i class="fas fa-user-plus mr-2"></i> Account & Authentication Credentials</h5>
                    <small class="text-white-50 font-weight-normal d-block">Set user personal details, login credentials, and permission role.</small>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="John Doe" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="example@gmail.com" value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Role</label>
                                <input type="text" class="form-control" value="sales-person" readonly>
                                <small class="text-muted">Role is predefined for this flow.</small>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Profile Picture</label>
                                <input type="file" name="avatar" class="form-control @error('avatar') is-invalid @enderror">
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="••••••••">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="••••••••">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions Bar -->
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{route('users.index')}}" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                    <button class="btn btn-success btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-check-circle mr-1"></i> Submit User</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('page-js')
    
@endpush