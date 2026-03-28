@extends('admin.layouts.app')

@push('page-css')

@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Product</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Edit Product</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">
			
			<!-- Edit Supplier -->
			<form method="post" enctype="multipart/form-data" action="{{route('suppliers.update',$supplier)}}">
				@csrf
				@method("PUT")
				<div class="service-fields mb-3">
					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Name<span class="text-danger">*</span></label>
								<input class="form-control" type="text" value="{{ old('name', $supplier->name) }}" name="name">
							</div>
						</div>
						<div class="col-lg-6">
							<label>Email</label>
							<input class="form-control" type="text" value="{{ old('email', $supplier->email) }}" name="email" >
						</div>
					</div>
				</div>

				<div class="service-fields mb-3">
					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Phone</label>
								<input class="form-control" type="text" value="{{ old('phone', $supplier->phone) }}" name="phone">
							</div>
						</div>
						<div class="col-lg-6">
							<label>Company</label>
							<select class="select2 form-control" name="company">
								<option value="">-- Select Company --</option>
								@foreach($companies as $company)
									<option value="{{$company->name}}" {{ (old('company') ? old('company') : $supplier->company) == $company->name ? 'selected' : '' }}>{{$company->name}}</option>
								@endforeach
							</select>
						</div>
					</div>
				</div>

				<div class="service-fields mb-3">
					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Address </label>
								<input type="text" name="address" value="{{ old('address', $supplier->address) }}" class="form-control">
							</div>
						</div>
						<div class="col-lg-6">
							<label>Product</label>
							<input type="text" name="product" value="{{ old('product', $supplier->product) }}" class="form-control">
						</div>
					</div>
				</div>	
				<div class="service-fields mb-3">
					<div class="row">
						<div class="col-12">
							<label>Comment</label>
							<textarea name="comment" class="form-control" cols="30" rows="10">{{ old('comment', $supplier->comment) }}</textarea>
						</div>
					</div>
				</div>		
				
				
				<div class="submit-section">
					<button class="btn btn-success submit-btn" type="submit" name="form_submit" value="submit">Submit</button>
				</div>
			</form>

			<!-- /Edit Supplier -->

			</div>
		</div>
	</div>			
</div>
@endsection	



@push('page-js')
	<!-- Select2 JS -->
	<script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>
@endpush




