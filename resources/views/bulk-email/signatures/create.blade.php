@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Create Signature</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.signatures.index')}}">Signatures</a></li>
		<li class="breadcrumb-item active">Create</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-8 offset-md-2">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.signatures.store') }}" method="POST" enctype="multipart/form-data">
					@csrf
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Full Name</label>
								<input type="text" name="name" class="form-control" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Designation</label>
								<input type="text" name="designation" class="form-control">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Company</label>
								<input type="text" name="company" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Phone</label>
								<input type="text" name="phone" class="form-control">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Website</label>
								<input type="url" name="website" class="form-control">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Public Email</label>
								<input type="email" name="email" class="form-control">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Address</label>
						<textarea name="address" class="form-control" rows="2"></textarea>
					</div>

					<div class="form-group">
						<label>Company/Personal Logo</label>
						<input type="file" name="logo" class="form-control" accept="image/*">
					</div>

					<div class="form-group">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" name="is_default" class="custom-control-input" id="is_default" value="1">
							<label class="custom-control-label" for="is_default">Set as default signature</label>
						</div>
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary btn-block">Save Signature</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
