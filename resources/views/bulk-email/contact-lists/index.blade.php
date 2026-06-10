@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Contact Lists</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Contact Lists</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('bec.contact-lists.create')}}" class="btn btn-primary float-right mt-2">Upload New List</a>
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
								<th>List Name</th>
								<th>Status</th>
								<th>Total Rows</th>
								<th>Processed</th>
								<th>Failed</th>
								<th>Duplicates</th>
								<th>Created At</th>
								<th class="text-right">Actions</th>
							</tr>
						</thead>
						<tbody>
							@foreach($lists as $list)
							<tr>
								<td>
									<a href="{{ route('bec.contacts.index', $list->id) }}">
										{{ $list->name }}
									</a>
								</td>
								<td>
									@if($list->status == 'pending')
										<span class="badge badge-warning">Pending</span>
									@elseif($list->status == 'processing')
										<span class="badge badge-info">Processing...</span>
									@elseif($list->status == 'completed')
										<span class="badge badge-success">Completed</span>
									@else
										<span class="badge badge-danger">Failed</span>
									@endif
								</td>
								<td>{{ $list->total_rows }}</td>
								<td>{{ $list->processed_rows }}</td>
								<td>{{ $list->failed_rows }}</td>
								<td>
									@if($list->duplicate_rows > 0)
										<a href="{{ route('bec.contact-lists.duplicates', $list->id) }}" class="text-warning font-weight-bold">
											{{ $list->duplicate_rows }}
										</a>
									@else
										0
									@endif
								</td>
								<td>{{ $list->created_at->format('M d, Y') }}</td>
								<td class="text-right">
									<div class="actions">
										<a href="{{ route('bec.contacts.index', $list->id) }}" class="btn btn-sm bg-success-light" title="View Contacts">
											<i class="fe fe-eye"></i>
										</a>
										@if($list->duplicate_rows > 0)
										<a href="{{ route('bec.contact-lists.duplicates', $list->id) }}" class="btn btn-sm bg-warning-light" title="View Duplicates">
											<i class="fe fe-users"></i>
										</a>
										@endif
										<form action="{{ route('bec.contact-lists.destroy', $list->id) }}" method="POST" style="display:inline">
											@csrf
											@method('DELETE')
											<button type="submit" class="btn btn-sm bg-danger-light" onclick="return confirm('Are you sure?')">
												<i class="fe fe-trash"></i> Delete
											</button>
										</form>
									</div>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="mt-4">
					{{ $lists->links() }}
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
