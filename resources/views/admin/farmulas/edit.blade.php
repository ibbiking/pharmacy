@extends('admin.layouts.app')

@push('page-css')

@endpush

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Farmula</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Edit Farmula</li>
	</ul>
</div>
@endpush
<!-- Visit codeastro.com for more projects -->
@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body custom-edit-service">
				

			<!-- Edit farmula -->
				<form method="POST" enctype="multipart/form-data" 
      action="{{ route('farmulas.update', $farmula->id) }}">
    @csrf
    @method('PUT')

    <div class="service-fields mb-3">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <label>Farmula Name<span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" value="{{ old('name', $farmula->name) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="service-fields mb-3">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <label>Descriptions</label>
                    <textarea class="form-control service-desc" name="description">{{ old('description', $farmula->description) }}</textarea>
                </div>
            </div>
        </div>
    </div>					
    
    <div class="submit-section">
        <button class="btn btn-success submit-btn" type="submit">Submit</button>
    </div>
</form>
			<!-- /Edit farmula -->
			</div>
		</div>
	</div>			
</div>
@endsection


@push('page-js')
	
@endpush