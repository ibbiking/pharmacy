@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Add Pharmacy Name</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Add Pharmacy Name</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">
                <form method="post" action="{{route('pharmacies.store')}}">
                    @csrf
                    <div class="service-fields mb-3">
                        <div class="row">
							<div class="col-lg-12">
								<div class="form-group">
									<label>Pharmacy Name<span class="text-danger">*</span></label>
									<input class="form-control" type="text" name="name" required>
								</div>
							</div>
                            <div class="col-lg-6">
								<div class="form-group">
									<label>Address</label>
									<input class="form-control" type="text" name="address">
								</div>
							</div>
                            <div class="col-lg-6">
								<div class="form-group">
									<label>Phone Number</label>
									<input class="form-control" type="text" name="phone">
								</div>
							</div>
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label>Note (Displayed on Receipts)</label>
                                    <textarea class="form-control" name="note" rows="3" maxlength="150" placeholder="Max 150 characters"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="submit-section">
                        <button class="btn btn-success submit-btn" type="submit" name="form_submit" value="submit">Submit</button>
                    </div>
                </form>
			</div>
		</div>
	</div>			
</div>
@endsection
