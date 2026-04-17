@extends('admin.layouts.app')
@section('content')
<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>{{ $title }}</h4>
            <h6>List of products with no defined stock limit</h6>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="stock-unaligned-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    $(document).ready(function() {
        $('#stock-unaligned-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('reports.stock_unaligned') }}",
            columns: [
                { data: 'product_name', name: 'product_name' },
                { data: 'company', name: 'company', orderable: false, searchable: false },
                { data: 'type', name: 'type', orderable: false, searchable: false },
                { 
                    data: 'action', 
                    name: 'action', 
                    orderable: false, 
                    searchable: false,
                    render: function(data, type, row) {
                        return '<a href="/admin/products/' + row.id + '/edit" class="btn btn-sm btn-primary">Setup Stock Limit</a>';
                    }
                }
            ]
        });
    });
</script>
@endpush
