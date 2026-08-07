@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
    <h3 class="page-title">Taxes</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Taxes</li>
    </ul>
</div>
<div class="col-sm-5 col">
    <a href="{{route('taxes.create')}}" class="btn btn-primary float-right mt-2 rounded-pill px-4 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Tax</a>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tax-table"
                        class="datatable table table-striped table-bordered table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Rate (%)</th>
                                <th>Created date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
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
    $('#tax-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('taxes.index') }}",
        columns: [
            {data: 'name', name: 'name'},
            {data: 'rate', name: 'rate'},
            {data: 'created_at', name: 'created_at'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });
});
</script>
@endpush