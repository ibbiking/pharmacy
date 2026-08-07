@extends('admin.layouts.app')

@push('page-css')
    
@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Role</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item active">Dashboard</li>
	</ul>
</div>
@endpush

@section('content')

<div class="row">
    <div class="col-md-12 col-lg-12">
        <form method="POST" action="{{route('roles.update',$role)}}">
            @csrf
            @method("PUT")
            
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
                <div class="card-header text-white py-3" style="background: linear-gradient(135deg, #0056b3, #007bff);">
                    <h5 class="card-title text-white mb-1"><i class="fas fa-shield-alt mr-2"></i> Role Name & Access Permissions</h5>
                    <small class="text-white-50 font-weight-normal d-block">Update user role titles and grant system module permissions.</small>
                </div>
                <div class="card-body p-4">
                    <div class="row mb-3">
                        <div class="col-lg-6">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Role Name <span class="text-danger">*</span></label>
                                <input type="text" name="role" value="{{$role->name}}" class="form-control" placeholder="e.g. manager" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold">Select Permissions</label>
                                <select class="select2 form-select form-control" name="permission[]" multiple="multiple"> 
                                    @foreach ($permissions as $permission)
                                        <option value="{{$permission->name}}" {{$role->hasPermissionTo($permission->name) ? 'selected': ''}}>{{$permission->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions Bar -->
            <div class="card mb-4 shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
                <div class="card-body p-3 d-flex justify-content-between align-items-center">
                    <a href="{{route('roles.index')}}" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-arrow-left mr-1"></i> Cancel</a>
                    <button class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-sync-alt mr-1"></i> Update Role</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection

@push('page-js')
    
@endpush