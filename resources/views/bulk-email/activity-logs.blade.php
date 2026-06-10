@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Bulk Email Activity Logs</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Activity Logs</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="card card-table">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover table-center mb-0">
						<thead>
							<tr>
								<th>User</th>
								<th>Action</th>
								<th>Target</th>
								<th>IP Address</th>
								<th>Date</th>
							</tr>
						</thead>
						<tbody>
							@forelse($logs as $log)
							<tr>
								<td>
                                    <div class="media align-items-center">
                                        <div class="media-body">
                                            <h5 class="m-0">{{ $log->user->name ?? 'System' }}</h5>
                                            <small class="text-muted">{{ $log->user->email ?? '' }}</small>
                                        </div>
                                    </div>
                                </td>
								<td>
                                    @php
                                        $icon = 'fe-activity';
                                        $color = 'primary';
                                        if(str_contains(strtolower($log->action), 'create')) { $icon = 'fe-plus'; $color = 'success'; }
                                        if(str_contains(strtolower($log->action), 'delete')) { $icon = 'fe-trash'; $color = 'danger'; }
                                        if(str_contains(strtolower($log->action), 'update')) { $icon = 'fe-edit'; $color = 'info'; }
                                        if(str_contains(strtolower($log->action), 'send')) { $icon = 'fe-send'; $color = 'warning'; }
                                    @endphp
                                    <span class="badge badge-pill badge-{{ $color }}">
                                        <i class="fe {{ $icon }}"></i> {{ $log->action }}
                                    </span>
                                </td>
								<td>
                                    <span class="text-info">{{ $log->model_type ? class_basename($log->model_type) : '-' }}</span>
                                    <small class="d-block text-muted">ID: {{ $log->model_id ?? '-' }}</small>
                                </td>
								<td><code>{{ $log->ip_address }}</code></td>
								<td>
                                    {{ $log->created_at->format('M d, Y') }}<br>
                                    <small class="text-muted">{{ $log->created_at->format('H:i:s') }} ({{ $log->created_at->diffForHumans() }})</small>
                                </td>
							</tr>
							@empty
							<tr>
								<td colspan="5" class="text-center">No activity logs found.</td>
							</tr>
							@endforelse
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
