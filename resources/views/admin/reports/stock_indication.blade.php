@extends('admin.layouts.app')
@section('content')
<div class="content">
    <div class="page-header">
        <div class="page-title">
            <h4>{{ $title }}</h4>
            <h6>List of products actively monitored under stock limits</h6>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="stock-indication-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Company</th>
                            <th>Target Category</th>
                            <th>Current Stock</th>
                            <th>Limit Qty</th>
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
        $('#stock-indication-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('reports.stock_indication') }}",
            columns: [
                { data: 'product_name', name: 'product_name' },
                { data: 'company.name', name: 'company.name', defaultContent: '-' },
                { data: 'category', name: 'category', orderable: false, searchable: false },
                { data: 'current_qty', name: 'current_qty', orderable: false, searchable: false },
                { data: 'limit_qty', name: 'limit_qty', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
