@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Signature: {{ $signature->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.signatures.index')}}">Signatures</a></li>
		<li class="breadcrumb-item active">Edit</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-8 offset-md-2">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.signatures.update', $signature->id) }}" method="POST" enctype="multipart/form-data">
					@csrf
					@method('PUT')
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Full Name</label>
								<input type="text" name="name" class="form-control" value="{{ $signature->name }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Designation</label>
								<input type="text" name="designation" class="form-control" value="{{ $signature->designation }}">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Company</label>
								<input type="text" name="company" class="form-control" value="{{ $signature->company }}">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Phone</label>
								<input type="text" name="phone" class="form-control" value="{{ $signature->phone }}">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Website</label>
								<input type="url" name="website" class="form-control" value="{{ $signature->website }}">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Public Email</label>
								<input type="email" name="email" class="form-control" value="{{ $signature->email }}">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Address</label>
						<textarea name="address" class="form-control" rows="2">{{ $signature->address }}</textarea>
					</div>

					<div class="form-group">
						<label>Change Logo</label>
						<input type="file" name="logo" class="form-control" accept="image/*">
						@if($signature->logo)
						<div class="mt-2">
							<img src="{{ Storage::url($signature->logo) }}" width="100">
						</div>
						@endif
					</div>

					<div class="form-group">
						<div class="custom-control custom-checkbox">
							<input type="checkbox" name="is_default" class="custom-control-input" id="is_default" value="1" {{ $signature->is_default ? 'checked' : '' }}>
							<label class="custom-control-label" for="is_default">Set as default signature</label>
						</div>
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary btn-block">Update Signature</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
