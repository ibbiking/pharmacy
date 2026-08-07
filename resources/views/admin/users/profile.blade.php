@extends('admin.layouts.app')

@push('page-header')
<div class="col">
	<h3 class="page-title">Profile</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Profile</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="card mb-4 border-primary shadow-sm" style="border-radius: 12px; overflow: hidden;">
			<div class="card-body p-4">
				<div class="row align-items-center">
					<div class="col-auto">
						<img class="rounded-circle shadow" alt="User Image" width="100" height="100" style="object-fit: cover; border: 3px solid #007bff;" src="{{!empty(auth()->user()->avatar) ? asset('storage/users/'.auth()->user()->avatar): asset('assets/img/avatar_1nn.png')}}">
					</div>
					<div class="col ml-md-n2">
						<h3 class="user-name mb-1 font-weight-bold">{{auth()->user()->name}}</h3>
						<h6 class="text-muted mb-2"><i class="fas fa-envelope mr-1"></i> {{auth()->user()->email}}</h6>
						<span class="badge badge-primary px-3 py-2" style="font-size: 0.85rem;">
							@foreach (auth()->user()->getRoleNames() as $role)
								<i class="fas fa-user-shield mr-1"></i> {{$role}}
							@endforeach
						</span>
					</div>
				</div>
			</div>
		</div>

		<div class="profile-menu mb-4">
			<ul class="nav nav-pills nav-justified bg-light p-2 rounded-lg" style="border-radius: 10px;">
				<li class="nav-item">
					<a class="nav-link active font-weight-bold" data-toggle="tab" href="#per_details_tab"><i class="fas fa-user mr-1"></i> Personal Details</a>
				</li>
				<li class="nav-item">
					<a class="nav-link font-weight-bold" data-toggle="tab" href="#password_tab"><i class="fas fa-key mr-1"></i> Change Password</a>
				</li>
			</ul>
		</div>

		<div class="tab-content profile-tab-cont">

			<!-- Personal Details Tab -->
			<div class="tab-pane fade show active" id="per_details_tab">

				<!-- Personal Details -->
				<div class="row">
					<div class="col-lg-12">
						<div class="card shadow-sm" style="border-radius: 12px; overflow: hidden; border: 1px solid #007bff;">
							<div class="card-header text-white py-3 d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #0056b3, #007bff);">
								<div>
									<h5 class="card-title text-white mb-1"><i class="fas fa-id-card mr-2"></i> User Profile Details</h5>
									<small class="text-white-50 font-weight-normal d-block">Manage your personal information, role badge, and account avatar.</small>
								</div>
								<a class="btn btn-light btn-sm rounded-pill px-3 shadow-sm font-weight-bold" data-toggle="modal" href="#edit_personal_details"><i class="fa fa-edit mr-1 text-primary"></i> Edit Details</a>
							</div>
							<div class="card-body p-4">
								<div class="row mb-3">
									<p class="col-sm-3 font-weight-bold text-muted mb-0">Full Name</p>
									<p class="col-sm-9 font-weight-semibold mb-0">{{auth()->user()->name}}</p>
								</div>

								<div class="row mb-3">
									<p class="col-sm-3 font-weight-bold text-muted mb-0">Email Address</p>
									<p class="col-sm-9 font-weight-semibold mb-0">{{auth()->user()->email}}</p>
								</div>

								<div class="row">
									<p class="col-sm-3 font-weight-bold text-muted mb-0">User Role</p>
									<p class="col-sm-9 font-weight-semibold mb-0">
										@foreach (auth()->user()->getRoleNames() as $role)
										<span class="badge badge-info px-2 py-1">{{$role}}</span>
										@endforeach
									</p>
								</div>

							</div>
						</div>

						<!-- Edit Details Modal -->
						<div class="modal fade" id="edit_personal_details" aria-hidden="true" role="dialog">
							<div class="modal-dialog modal-dialog-centered" role="document">
								<div class="modal-content" style="border-radius: 15px; overflow: hidden;">
									<div class="modal-header bg-primary text-white">
										<h5 class="modal-title text-white"><i class="fas fa-user-edit mr-2"></i> Edit Personal Details</h5>
										<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
											<span aria-hidden="true">&times;</span>
										</button>
									</div>
									<div class="modal-body p-4">
										<form method="POST" enctype="multipart/form-data" action="{{route('profile.update',auth()->user())}}">
											@csrf
											<div class="row form-row">
												<div class="col-12 mb-3">
													<div class="form-group mb-0">
														<label class="font-weight-bold">Full Name</label>
														<input class="form-control" name="name" type="text" value="{{auth()->user()->name}}" placeholder="Full Name" required>
													</div>
												</div>
												<div class="col-12 mb-3">
													<div class="form-group mb-0">
														<label class="font-weight-bold">Email Address</label>
														<input class="form-control" name="email" type="text" value="{{auth()->user()->email}}" placeholder="Email" {{ auth()->user()->hasRole('sales-person') ? 'readonly' : '' }}>
														@if(auth()->user()->hasRole('sales-person'))
															<small class="text-muted">Sales person email is managed by business owner.</small>
														@endif
													</div>
												</div>
												<div class="col-12 mb-3">
													<div class="form-group mb-0">
														<label class="font-weight-bold">Profile Picture</label>
														<input type="file" value="{{auth()->user()->avatar}}" class="form-control" name="avatar">
													</div>
												</div>

											</div>
											<button type="submit" class="btn btn-success btn-block btn-lg rounded-pill shadow-sm"><i class="fas fa-check-circle mr-1"></i> Save Changes</button>
										</form>
									</div>
								</div>
							</div>
						</div>
						<!-- /Edit Details Modal -->

					</div>
				</div>
				<!-- /Personal Details -->

			</div>
			<!-- /Personal Details Tab -->

			<!-- Change Password Tab -->
			<div id="password_tab" class="tab-pane fade">

				<div class="card shadow-sm" style="border-radius: 12px; border: 1px solid #e3e8ee;">
					<div class="card-header bg-light py-3">
						<h5 class="card-title text-dark mb-0"><i class="fas fa-lock text-primary mr-2"></i> Update Security Password</h5>
					</div>
					<div class="card-body p-4">
						<div class="row">
							<div class="col-md-10 col-lg-12">
								<form method="POST" action="{{route('update-password',auth()->user())}}">
									@csrf
									@method("PUT")
									<div class="form-group mb-3">
										<label class="font-weight-bold">Current Password</label>
										<input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required>
									</div>
									<div class="form-group mb-3">
										<label class="font-weight-bold">New Password</label>
										<input type="password" name="password" class="form-control" placeholder="Enter your new password" required>
									</div>
									<div class="form-group mb-4">
										<label class="font-weight-bold">Confirm Password</label>
										<input type="password" name="password_confirmation" class="form-control" placeholder="Repeat your new password" required>
									</div>
									<button class="btn btn-success btn-lg rounded-pill px-5 shadow-sm" type="submit"><i class="fas fa-check-circle mr-1"></i> Update Password</button>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- /Change Password Tab -->

		</div>
	</div>
</div>
@endsection