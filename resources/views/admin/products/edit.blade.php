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
<!-- Visit codeastro.com for more projects -->
@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">


				<!-- Edit Product -->
				<form method="post" enctype="multipart/form-data" id="update_service"
					action="{{route('products.update',$product)}}">
					@csrf
					@method("PUT")

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-12">
								<div class="form-group">
									<label>Product Name<span class="text-danger">*</span></label>
									<input class="form-control" type="text" name="product_name"
										value="{{$product->product_name}}">
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Company <span class="text-danger">*</span></label>
								<select name="company_id" class="form-control" required>
									<option value="">-- Select Company --</option>
									@foreach($companies as $company)
									<option value="{{ $company->id }}" {{ $product->company_id == $company->id ?
										'selected' : '' }}>
										{{ $company->name }}
									</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label>Formula <span class="text-danger">*</span></label>
								<select name="farmula_id" class="form-control" required>
									<option value="">-- Select Formula --</option>
									@foreach($farmulas as $farmula)
									<option value="{{ $farmula->id }}" {{ $product->farmula_id == $farmula->id ?
										'selected' : '' }}>
										{{ $farmula->name }}
									</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label>Product Type <span class="text-danger">*</span></label>
								<select name="product_type_id" class="form-control" required>
									<option value="">-- Select Product Type --</option>
									@foreach($productTypes as $type)
									<option value="{{ $type->id }}" {{ $product->product_type_id == $type->id ?
										'selected' : '' }}>
										{{ $type->name }}
									</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label>Strength <span class="text-danger">*</span></label>
								<select name="strength_id" class="form-control" required>
									<option value="">-- Select Strength --</option>
									@foreach($strengths as $strength)
									<option value="{{ $strength->id }}" {{ $product->strength_id == $strength->id ?
										'selected' : '' }}>
										{{ $strength->name }}
									</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-lg-3">
							<div class="form-group">
								<label>Rack / Location</label>
								<input type="text" name="rack" class="form-control" value="{{ $product->rack }}">
							</div>
						</div>
						<div class="col-lg-3">
							<div class="form-group">
								<label>Barcode</label>
								<input type="text" name="barcode" class="form-control" value="{{ $product->barcode }}">
							</div>
						</div>

						<div class="col-lg-3">
							<div class="form-group">
								<label>Discount</label>
								<input type="number" step="0.01" name="discount" class="form-control"
									value="{{ $product->discount }}">
							</div>
						</div>

						<div class="col-lg-3">
							<div class="form-group">
								<label>Discount (%)</label>
								<input type="number" step="0.01" name="discount_percent" class="form-control"
									value="{{ $product->discount_percent }}">
							</div>
						</div>

						<div class="col-lg-3">
							<div class="form-check" style="margin-top: 32px;">
								<input type="checkbox" class="form-check-input" name="lock_max_discount" value="1"
									id="lockMaxDiscount" {{ $product->lock_max_discount ? 'checked' : '' }}>
								<label class="form-check-label" for="lockMaxDiscount">Lock Max Discount</label>
							</div>
						</div>
					</div>

					<div class="service-fields mb-3">
						<div class="row">
							<div class="col-lg-12">
								<div class="form-group">
									<label>Descriptions <span class="text-danger">*</span></label>
									<textarea class="form-control service-desc" value="{{$product->description}}"
										name="description">{{$product->description}}</textarea>
								</div>
							</div>

						</div>
					</div>

					<div class="submit-section">
						<button class="btn btn-success submit-btn" type="submit" name="form_submit"
							value="submit">Submit</button>
					</div>
				</form>
				<!-- /Edit Product -->
			</div>
		</div>
	</div>
</div>
@endsection


@push('page-js')

@endpush