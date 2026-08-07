@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')

@endpush

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Farmulas</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Farmulas</li>
	</ul>
</div>
<div class="col-sm-5 col">
	<a href="{{route('farmulas.create')}}" class="btn btn-primary float-right mt-2 rounded-pill px-4 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Formula</a>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-sm-12">
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table id="farmula-table"
						class="datatable table table-striped table-bordered table-hover table-center mb-0">
						<thead>
							<tr style="boder:1px solid black;">
								<th>Name</th>
								<th>Created date</th>
								<th class="text-center action-btn">Actions</th>
							</tr>
						</thead>
						<tbody>

						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script>
	$(document).ready(function() {
        var table = $('#farmula-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{route('farmulas.index')}}",
            columns: [
    {data: 'name', name: 'name'},
    {data: 'created_at', name: 'created_at'},
    {data: 'action', name: 'action', orderable: false, searchable: false},
]
        });
        //
    });
</script>
@endpush