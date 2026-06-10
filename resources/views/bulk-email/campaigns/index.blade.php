@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Campaigns</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Campaigns</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('bec.campaigns.create')}}" class="btn btn-primary float-right mt-2">New Campaign</a>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover table-center mb-0">
						<thead>
							<tr>
								<th>Campaign Name</th>
								<th>List</th>
								<th>Template</th>
								<th>Status</th>
								<th>Scheduled at</th>
								<th class="text-right">Actions</th>
							</tr>
						</thead>
						<tbody>
							@foreach($campaigns as $campaign)
							<tr>
								<td>{{ $campaign->name }}</td>
								<td>{{ $campaign->contactList->name }}</td>
								<td>{{ $campaign->template->name }}</td>
								<td>
									@php
										$badge = 'secondary';
										if($campaign->status == 'scheduled') $badge = 'primary';
										if($campaign->status == 'processing') $badge = 'info';
										if($campaign->status == 'completed') $badge = 'success';
										if($campaign->status == 'failed') $badge = 'danger';
									@endphp
									<span class="badge badge-{{ $badge }}">{{ ucfirst($campaign->status) }}</span>
								</td>
								<td>{{ $campaign->scheduled_at ? $campaign->scheduled_at->format('M d, H:i') : 'Immediate' }}</td>
								<td class="text-right">
									<div class="actions">
										<a href="{{ route('bec.campaigns.show', $campaign->id) }}" class="btn btn-sm bg-success-light">
											<i class="fe fe-eye"></i> View Stats
										</a>
										@if(in_array($campaign->status, ['draft', 'scheduled']))
										<a href="{{ route('bec.campaigns.edit', $campaign->id) }}" class="btn btn-sm bg-info-light">
											<i class="fe fe-pencil"></i> Edit
										</a>
										@endif
										<form action="{{ route('bec.campaigns.send', $campaign->id) }}" method="POST" style="display:inline">
											@csrf
											<button type="submit" class="btn btn-sm btn-primary">Send Now (Instant)</button>
										</form>
									</div>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="mt-4">
					{{ $campaigns->links() }}
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
