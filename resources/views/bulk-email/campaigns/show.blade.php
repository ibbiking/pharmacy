@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Campaign Details: {{ $campaign->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.campaigns.index')}}">Campaigns</a></li>
		<li class="breadcrumb-item active">Details</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-4">
		<div class="card">
			<div class="card-header">
				<h4 class="card-title">Campaign Summary</h4>
			</div>
			<div class="card-body">
				<p><strong>Status:</strong> <span class="badge badge-info">{{ ucfirst($campaign->status) }}</span></p>
				<p><strong>Contact List:</strong> {{ $campaign->contactList->name }} ({{ $campaign->contactList->total_rows }} contacts)</p>
				<p><strong>Email Template:</strong> {{ $campaign->template->name }}</p>
				<p><strong>Sender Name:</strong> {{ $campaign->from_name ?: 'Global Default' }}</p>
				@if($campaign->signature)
				<p><strong>Signature:</strong> {{ $campaign->signature->name }}</p>
				@endif
                <p><strong>Subject:</strong> {{ $campaign->subject ?: $campaign->template->subject }}</p>
				<p><strong>Created:</strong> {{ $campaign->created_at->format('M d, Y H:i') }}</p>
                @if($campaign->scheduled_at)
                <p><strong>Scheduled For:</strong> {{ $campaign->scheduled_at->format('M d, Y H:i') }}</p>
                @endif
			</div>
		</div>

        @if($campaign->attachments || $campaign->template->attachments)
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Attachments</h4>
            </div>
            <div class="card-body">
                @if($campaign->attachments)
                    <h6>Campaign Direct:</h6>
                    <ul class="list-unstyled">
                        @foreach($campaign->attachments as $attach)
                        <li><i class="fa fa-paperclip"></i> {{ $attach['name'] }}</li>
                        @endforeach
                    </ul>
                @endif
                @if($campaign->template->attachments)
                    <h6>From Template:</h6>
                    <ul class="list-unstyled">
                        @foreach($campaign->template->attachments as $attach)
                        <li><i class="fa fa-paperclip"></i> {{ $attach['name'] }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        @endif
	</div>

	<div class="col-md-8">
		<div class="row">
			<div class="col-md-4">
				<div class="card bg-success text-white text-center p-3">
					<h4>Delivered</h4>
					<h2>{{ $campaign->logs()->where('status', 'sent')->count() }}</h2>
				</div>
			</div>
            @php
                $opens = $campaign->logs()->where('status', 'opened')->count();
                $clicks = $campaign->logs()->where('status', 'clicked')->count();
            @endphp
            @if($opens > 0)
			<div class="col-md-4">
				<div class="card bg-primary text-white text-center p-3">
					<h4>Opened</h4>
					<h2>{{ $opens }}</h2>
				</div>
			</div>
            @endif
            @if($clicks > 0)
			<div class="col-md-4">
				<div class="card bg-info text-white text-center p-3">
					<h4>Clicked</h4>
					<h2>{{ $clicks }}</h2>
				</div>
			</div>
            @endif
		</div>

		<div class="card">
			<div class="card-header">
				<h4 class="card-title">Detailed Logs</h4>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Email</th>
								<th>Status</th>
								<th>Opened At</th>
								<th>Error</th>
							</tr>
						</thead>
						<tbody>
							@foreach($logs as $log)
							<tr>
								<td>{{ $log->email }}</td>
								<td>
									@php
										$badge = 'secondary';
										if($log->status == 'sent') $badge = 'success';
										if($log->status == 'opened') $badge = 'primary';
										if($log->status == 'clicked') $badge = 'info';
										if($log->status == 'failed') $badge = 'danger';
									@endphp
									<span class="badge badge-{{ $badge }}">{{ ucfirst($log->status) }}</span>
								</td>
								<td>{{ $log->opened_at ? $log->opened_at->diffForHumans() : '-' }}</td>
								<td>{{ $log->error ? Str::limit($log->error, 50) : '-' }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="mt-4">
					{{ $logs->links() }}
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
