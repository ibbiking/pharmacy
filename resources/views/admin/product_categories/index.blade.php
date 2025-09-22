@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
    <h3 class="page-title">Product Categories</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Product Categories</li>
    </ul>
</div>
<div class="col-sm-5 col">
    <a href="{{route('product-categories.create',['product_id'=>1])}}" class="btn btn-success float-right mt-2">Add Relation</a>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="pc-table" class="datatable table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Parent</th>
                                <th>Child</th>
                                <th>Action</th>
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
$(function() {
    $('#pc-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('product-categories.index') }}",
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex' },
            { data: 'parent', name: 'parent' },
            { data: 'child', name: 'child' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ]
    });
});
</script>
@endpush