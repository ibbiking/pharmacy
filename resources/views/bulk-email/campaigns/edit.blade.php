@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Edit Campaign: {{ $campaign->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.campaigns.index')}}">Campaigns</a></li>
		<li class="breadcrumb-item active">Edit</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-10 offset-md-1">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.campaigns.update', $campaign->id) }}" method="POST" enctype="multipart/form-data">
					@csrf
                    @method('PUT')
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Campaign Name</label>
								<input type="text" name="name" class="form-control" value="{{ $campaign->name }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Sender Name (From Name)</label>
								<input type="text" name="from_name" class="form-control" value="{{ $campaign->from_name }}" placeholder="e.g. Bulk Campaign Manager">
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
									<option value="{{ $list->id }}" {{ $campaign->contact_list_id == $list->id ? 'selected' : '' }}>{{ $list->name }} ({{ $list->total_contacts }} contacts)</option>
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
									<option value="{{ $template->id }}" {{ $campaign->template_id == $template->id ? 'selected' : '' }}>{{ $template->name }}</option>
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
									<option value="{{ $sig->id }}" {{ $campaign->signature_id == $sig->id ? 'selected' : '' }}>{{ $sig->name }}</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>Scheduled at (Leave empty for Draft/Immediate)</label>
								<input type="datetime-local" name="scheduled_at" class="form-control" value="{{ $campaign->scheduled_at ? $campaign->scheduled_at->format('Y-m-d\TH:i') : '' }}" min="{{ date('Y-m-d\TH:i') }}">
							</div>
						</div>
					</div>

					<div class="form-group">
						<label>Email Subject (Overrides template subject if provided)</label>
						<input type="text" name="subject" class="form-control" value="{{ $campaign->subject }}" placeholder="Enter final subject line (Optional)">
					</div>

					<div class="form-group">
						<label>Attachments (Direct uploads for this campaign)</label>
						<input type="file" name="files[]" class="form-control" multiple>
						<small class="text-muted">Upload new files to add to campaign attachments. Max 10MB per file.</small>
						
						@if($campaign->attachments && count($campaign->attachments) > 0)
						<div class="mt-2">
							<h6>Current Campaign Attachments:</h6>
							<ul class="list-unstyled">
								@foreach($campaign->attachments as $attach)
								<li>
									<i class="fa fa-paperclip"></i> {{ $attach['name'] }} ({{ number_format($attach['size']/1024, 2) }} KB)
								</li>
								@endforeach
							</ul>
						</div>
						@endif
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary btn-block">Update Campaign</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
