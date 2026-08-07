@extends('admin.layouts.app')

@push('page-css')
    
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit User</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item active">Dashboard</li>
	</ul>
</div>
@endpush

@section('content')

<div class="row">
    <div class="col-md-12 col-lg-12">
        <form method="POST" enctype="multipart/form-data" action="{{route('users.update',$user)}}">
            @csrf
            @method("PUT")
            
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
                <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #0056b3, #007bff);">
                    <h5 class="card-title text-white mb-1"><i class="fas fa-user-edit mr-2"></i> Account & Authentication Credentials</h5>
                    <small class="text-white-50 font-weight-normal d-block">Update user personal details, login email, password, and system role.</small>
                </div>
                <div class="card-body p-4">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{$user->name}}" placeholder="John Doe" required>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" value="{{$user->email}}" placeholder="example@gmail.com" required>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Role</label>
                                <select class="select2 form-select form-control" name="role">
                                    @foreach ($roles as $role)
                                        <option value="{{$role->name}}" {{ $user->hasRole($role->name) ? 'selected' : '' }}>{{$role->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-3">
                                <label class="font-weight-bold">Profile Picture</label>
                                <input type="file" name="avatar" class="form-control">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">New Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep unchanged">
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Confirm New Password</label>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Leave blank to keep unchanged">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions Bar -->
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{route('users.index')}}" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                    <button class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-sync-alt mr-1"></i> Update User</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('page-js')
    
@endpush