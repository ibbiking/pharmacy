@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
    <h3 class="page-title">Manage Businesses</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Businesses</li>
    </ul>
</div>
<div class="col-sm-5 col">
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="businesses-table" class="datatable table table-striped table-bordered table-hover table-center mb-0">
                        <thead>
                            <tr style="border:1px solid black;">
                                <th>#</th>
                                <th>Business Name</th>
                                <th>Owner</th>
                                <th>Address</th>
                                <th>Contact</th>
                                <th class="text-center action-btn">Action</th>
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
        var table = $('#businesses-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('superadmin.businesses') }}",
            columns: [
                {data: 'id', name: 'id'},
                {data: 'name', name: 'name'},
                {data: 'owner', name: 'owner'},
                {data: 'address', name: 'address'},
                {data: 'phone', name: 'phone'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
</script>
@endpush
