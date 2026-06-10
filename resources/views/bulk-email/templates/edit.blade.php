@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Template: {{ $template->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.templates.index')}}">Templates</a></li>
		<li class="breadcrumb-item active">Edit</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.templates.update', $template->id) }}" method="POST" enctype="multipart/form-data">
					@csrf
					@method('PUT')
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Template Name</label>
								<input type="text" name="name" class="form-control" value="{{ $template->name }}" required>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Target Contact List</label>
								<select name="contact_list_id" id="contact_list_id" class="form-control select">
									<option value="">General (No specific list)</option>
									@foreach($lists as $list)
									<option value="{{ $list->id }}" {{ $template->contact_list_id == $list->id ? 'selected' : '' }}>{{ $list->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>Subject Line</label>
								<input type="text" name="subject" class="form-control" value="{{ $template->subject }}" required>
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Email Body</label>
						<textarea name="body" id="editor" class="form-control" rows="15">{{ $template->body }}</textarea>
					</div>

					<div class="form-group">
						<label>Attachments</label>
						<input type="file" name="files[]" class="form-control" multiple>
						<small class="text-muted">Upload new files to add to attachments. Max 10MB per file.</small>
						
						@if($template->attachments && count($template->attachments) > 0)
						<div class="mt-2">
							<h6>Current Attachments:</h6>
							<ul class="list-unstyled">
								@foreach($template->attachments as $attach)
								<li>
									<i class="fa fa-paperclip"></i> {{ $attach['name'] }} ({{ number_format($attach['size']/1024, 2) }} KB)
								</li>
								@endforeach
							</ul>
						</div>
						@endif
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
						<button type="submit" class="btn btn-primary">Update Template</button>
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
    }).trigger('change');
</script>
@endpush
