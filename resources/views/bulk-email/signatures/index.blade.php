@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Signatures</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Signatures</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('bec.signatures.create')}}" class="btn btn-primary float-right mt-2">Add Signature</a>
</div>
@endpush

@section('content')
<div class="row">
	@foreach($signatures as $sig)
	<div class="col-md-4">
		<div class="card">
			<div class="card-body">
				<div class="d-flex align-items-center mb-3">
					@if($sig->logo)
					<img src="{{ Storage::url($sig->logo) }}" class="rounded-circle mr-3" width="50" height="50">
					@endif
					<div>
						<h5 class="mb-0">{{ $sig->name }}</h5>
						<small class="text-muted">{{ $sig->designation }}</small>
					</div>
					@if($sig->is_default)
					<span class="ml-auto badge badge-success">Default</span>
					@endif
				</div>
				<p class="small mb-1"><strong>Company:</strong> {{ $sig->company }}</p>
				<p class="small mb-1"><strong>Email:</strong> {{ $sig->email }}</p>
				<hr>
				<div class="d-flex justify-content-between">
					<a href="{{ route('bec.signatures.edit', $sig->id) }}" class="btn btn-sm btn-info">Edit</a>
					<form action="{{ route('bec.signatures.destroy', $sig->id) }}" method="POST">
						@csrf @method('DELETE')
						<button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</button>
					</form>
				</div>
			</div>
		</div>
	</div>
	@endforeach
</div>
@endsection
