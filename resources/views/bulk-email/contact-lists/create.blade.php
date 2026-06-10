@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Upload Contact List</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.contact-lists.index')}}">Contact Lists</a></li>
		<li class="breadcrumb-item active">Upload</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-8 offset-md-2">
		<div class="card">
			<div class="card-header">
				<h4 class="card-title">List Details</h4>
			</div>
			<div class="card-body">
				<form action="{{ route('bec.contact-lists.store') }}" method="POST" enctype="multipart/form-data">
					@csrf
					<div class="form-group">
						<label>List Name <span class="text-danger">*</span></label>
						<input type="text" name="name" class="form-control" value="{{ old('name') }}" required placeholder="e.g. Prospects_June_2026">
					</div>

					<div class="form-group">
						<label>Upload File (CSV, XLSX, or TXT) <span class="text-danger">*</span></label>
						<input type="file" name="file" class="form-control" required accept=".csv,.xlsx,.xls,.txt">
						<small class="text-muted">
							Rules: 
							<ul>
								<li>File must contain an <strong>email</strong> column.</li>
								<li>Headers must NOT contain spaces (use underscores, e.g., <strong>first_name</strong>).</li>
								<li>Maximum file size: 50MB (accepts .csv, .xlsx, .txt).</li>
							</ul>
						</small>
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary btn-block">Upload & Process</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
