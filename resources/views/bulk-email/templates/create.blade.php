@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Create Template</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.templates.index')}}">Templates</a></li>
		<li class="breadcrumb-item active">Create</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.templates.store') }}" method="POST" enctype="multipart/form-data">
					@csrf
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Template Name</label>
								<input type="text" name="name" class="form-control" required placeholder="e.g. Welcome Email">
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Target Contact List</label>
								<select name="contact_list_id" id="contact_list_id" class="form-control select">
									<option value="">General (No specific list)</option>
									@foreach($lists as $list)
									<option value="{{ $list->id }}">{{ $list->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Subject Line</label>
								<input type="text" name="subject" class="form-control" required placeholder="e.g. Hi @{{ first_name }}, Welcome to our service!">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Email Body</label>
						<textarea name="body" id="editor" class="form-control" rows="15"></textarea>
					</div>

					<div class="form-group">
						<label>Attachments</label>
						<input type="file" name="files[]" class="form-control" multiple>
						<small class="text-muted">You can select multiple files. Max 10MB per file.</small>
					</div>

					<div class="card bg-light mt-3">
						<div class="card-body">
							<h6>Available Merge Tags:</h6>
							<div id="merge-tags-container">
								<code>@{{ email }}</code>
							</div>
							<small class="text-muted d-block mt-1">Select a contact list to see more tags. Copy and paste these into your body or subject.</small>
						</div>
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary">Save Template</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script src="https://cdn.ckeditor.com/ckeditor5/35.0.1/classic/ckeditor.js"></script>
<script>
    ClassicEditor
        .create( document.querySelector( '#editor' ) )
        .catch( error => {
            console.error( error );
        } );

    $('#contact_list_id').on('change', function() {
        var listId = $(this).val();
        var container = $('#merge-tags-container');
        container.html('<code>@{{ email }}</code> ');

        if (listId) {
            $.get("{{ url('admin/bulk-email/contact-lists') }}/" + listId + "/columns", function(columns) {
                columns.forEach(function(col) {
                    if (col.column_name !== 'email') {
                        container.append('<code>@{{ ' + col.column_name + ' }}</code> ');
                    }
                });
            });
        }
    });
</script>
@endpush
