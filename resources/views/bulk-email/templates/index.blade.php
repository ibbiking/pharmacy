@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Email Templates</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Templates</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('bec.templates.create')}}" class="btn btn-primary float-right mt-2">Create New Template</a>
</div>
@endpush

@section('content')
<div class="row">
	@foreach($templates as $template)
	<div class="col-md-4">
		<div class="card">
			<div class="card-body">
				<h5 class="card-title">{{ $template->name }}</h5>
				<p class="text-muted small"><strong>Subject:</strong> {{ $template->subject }}</p>
				<hr>
				<div class="d-flex justify-content-between">
					<a href="{{ route('bec.templates.edit', $template->id) }}" class="btn btn-sm btn-info">Edit</a>
					<form action="{{ route('bec.templates.destroy', $template->id) }}" method="POST">
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
