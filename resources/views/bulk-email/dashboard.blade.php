@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Bulk Email Campaigns Dashboard</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item active">Dashboard</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-xl-3 col-sm-6 col-12">
		<div class="card">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-primary border-primary">
						<i class="fe fe-users"></i>
					</span>
					<div class="dash-count">
						<h3>{{ $stats['total_lists'] }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Total Lists</h6>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-sm-6 col-12">
		<div class="card">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-info">
						<i class="fe fe-user-plus"></i>
					</span>
					<div class="dash-count">
						<h3>{{ number_format($stats['total_contacts']) }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Total Contacts</h6>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-sm-6 col-12">
		<div class="card">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-success">
						<i class="fe fe-mail"></i>
					</span>
					<div class="dash-count">
						<h3>{{ $stats['total_campaigns'] }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Total Campaigns</h6>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-sm-6 col-12">
		<div class="card">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-warning">
						<i class="fe fe-check-square"></i>
					</span>
					<div class="dash-count">
						<h3>{{ $stats['completed_campaigns'] }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Completed</h6>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
    <div class="col-xl-3 col-sm-6 col-12">
		<div class="card bg-primary-light">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-primary">
						<i class="fe fe-send"></i>
					</span>
					<div class="dash-count">
						<h3>{{ number_format($stats['total_sent']) }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Emails Sent</h6>
				</div>
			</div>
		</div>
	</div>
    <div class="col-xl-3 col-sm-6 col-12">
		<div class="card bg-success-light">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-success">
						<i class="fe fe-eye"></i>
					</span>
					<div class="dash-count">
						<h3>{{ number_format($stats['total_opened']) }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Total Opens</h6>
                    <small>{{ $stats['total_sent'] > 0 ? round(($stats['total_opened'] / $stats['total_sent']) * 100, 1) : 0 }}% Open Rate</small>
				</div>
			</div>
		</div>
	</div>
    <div class="col-xl-3 col-sm-6 col-12">
		<div class="card bg-info-light">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-info">
						<i class="fe fe-external-link"></i>
					</span>
					<div class="dash-count">
						<h3>{{ number_format($stats['total_clicked']) }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Total Clicks</h6>
                    <small>{{ $stats['total_sent'] > 0 ? round(($stats['total_clicked'] / $stats['total_sent']) * 100, 1) : 0 }}% Click Rate</small>
				</div>
			</div>
		</div>
	</div>
    <div class="col-xl-3 col-sm-6 col-12">
		<div class="card bg-danger-light">
			<div class="card-body">
				<div class="dash-widget-header">
					<span class="dash-widget-icon text-danger">
						<i class="fe fe-activity"></i>
					</span>
					<div class="dash-count">
						<h3>{{ $stats['active_campaigns'] }}</h3>
					</div>
				</div>
				<div class="dash-widget-info">
					<h6 class="text-muted">Active Sending</h6>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="card card-table">
			<div class="card-header">
				<h4 class="card-title">Recent Activity</h4>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover table-center mb-0">
						<thead>
							<tr>
								<th>User</th>
								<th>Action</th>
								<th>IP Address</th>
								<th>Date</th>
							</tr>
						</thead>
						<tbody>
							@forelse($recent_activity as $activity)
							<tr>
								<td>{{ $activity->user->name ?? 'System' }}</td>
								<td>{{ $activity->action }}</td>
								<td>{{ $activity->ip_address }}</td>
								<td>{{ $activity->created_at->diffForHumans() }}</td>
							</tr>
							@empty
							<tr>
								<td colspan="4" class="text-center">No recent activity.</td>
							</tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
