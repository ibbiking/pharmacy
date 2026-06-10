@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Import Progress: {{ $contact_list->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.contact-lists.index')}}">Contact Lists</a></li>
		<li class="breadcrumb-item active">Import Progress</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-8 offset-md-2">
		<div class="card">
			<div class="card-body text-center">
				<h4 id="status-text">Processing your file...</h4>
				
				<div class="progress mt-4 mb-4" style="height: 30px;">
					<div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
				</div>

				<div class="row">
					<div class="col-4">
						<h5 class="text-muted">Total</h5>
						<h3 id="count-total">0</h3>
					</div>
					<div class="col-4">
						<h5 class="text-success">Processed</h5>
						<h3 id="count-processed">0</h3>
					</div>
					<div class="col-4">
						<h5 class="text-danger">Failed</h5>
						<h3 id="count-failed">0</h3>
					</div>
				</div>

				<div id="error-alert" class="alert alert-danger mt-4" style="display: none;"></div>

				<div id="completion-actions" class="mt-5" style="display: none;">
					<a href="{{ route('bec.contacts.index', $contact_list->id) }}" class="btn btn-success">View Contacts</a>
					<a href="{{ route('bec.contact-lists.index') }}" class="btn btn-outline-secondary">Back to Lists</a>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script>
$(document).ready(function() {
    var checkInterval = setInterval(function() {
        $.get("{{ route('bec.contact-lists.progress', $contact_list->id) }}", function(res) {
            $('#progress-bar').css('width', res.percentage + '%').text(res.percentage + '%');
            $('#count-total').text(res.total);
            $('#count-processed').text(res.processed);
            $('#count-failed').text(res.failed);

            if(res.status == 'completed') {
                clearInterval(checkInterval);
                $('#status-text').text('Import Completed!').addClass('text-success');
                $('#progress-bar').removeClass('progress-bar-animated').addClass('bg-success');
                $('#completion-actions').fadeIn();
            } else if(res.status == 'failed') {
                clearInterval(checkInterval);
                $('#status-text').text('Import Failed').addClass('text-danger');
                $('#progress-bar').removeClass('progress-bar-animated').addClass('bg-danger');
                $('#error-alert').text(res.error || 'Unknown error occurred during processing.').show();
                $('#completion-actions').fadeIn();
            }
        });
    }, 2000);
});
</script>
@endpush
