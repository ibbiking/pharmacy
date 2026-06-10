@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Create Campaign</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.campaigns.index')}}">Campaigns</a></li>
		<li class="breadcrumb-item active">Create</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-10 offset-md-1">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.campaigns.store') }}" method="POST" enctype="multipart/form-data">
					@csrf
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Campaign Name</label>
								<input type="text" name="name" class="form-control" required placeholder="e.g. June Newsletter">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Sender Name (From Name)</label>
								<input type="text" name="from_name" class="form-control" placeholder="e.g. Bulk Campaign Manager">
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Contact List</label>
								<select name="contact_list_id" class="form-control select" required>
									<option value="">Select List</option>
									@foreach($lists as $list)
									<option value="{{ $list->id }}">{{ $list->name }} ({{ $list->total_rows }} contacts)</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Email Template</label>
								<select name="template_id" class="form-control select" required>
									<option value="">Select Template</option>
									@foreach($templates as $template)
									<option value="{{ $template->id }}">{{ $template->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Signature</label>
								<select name="signature_id" class="form-control select">
									<option value="">No Signature</option>
									@foreach($signatures as $sig)
									<option value="{{ $sig->id }}" {{ $sig->is_default ? 'selected' : '' }}>{{ $sig->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Scheduled at (Leave empty for Draft/Immediate)</label>
								<input type="datetime-local" name="scheduled_at" class="form-control" min="{{ date('Y-m-d\TH:i') }}">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Email Subject (Overrides template subject if provided)</label>
						<input type="text" name="subject" class="form-control" placeholder="Enter final subject line (Optional)">
					</div>

					<div class="form-group">
						<label>Attachments (Direct uploads for this campaign)</label>
						<input type="file" name="files[]" class="form-control" multiple>
						<small class="text-muted">These attachments will be sent in addition to any template attachments.</small>
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary btn-block">Create Campaign</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script>
$(document).ready(function() {
    $('select[name="template_id"]').on('change', function() {
        var templateId = $(this).val();
        // Option to fetch template subject via AJAX if needed
    });
});
</script>
@endpush
