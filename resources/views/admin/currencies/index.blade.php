@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
    <h3 class="page-title">Currencies</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Currencies</li>
    </ul>
</div>
<div class="col-sm-5 col">
    <a href="{{route('currencies.create')}}" class="btn btn-primary float-right mt-2 rounded-pill px-4 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Currency</a>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-3">
                    Global currencies are shared by every business and can only be edited by a super-admin.
                    Currencies you add yourself are visible only to your business.
                </p>
                <div class="table-responsive">
                    <table id="currency-table"
                        class="datatable table table-striped table-bordered table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Symbol</th>
                                <th>Exchange Rate</th>
                                <th>Scope</th>
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
    $('#currency-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('currencies.index') }}",
        columns: [
            {data: 'currency_code', name: 'currency_code'},
            {data: 'name', name: 'name'},
            {data: 'symbol', name: 'symbol'},
            {data: 'exchange_rate', name: 'exchange_rate'},
            {data: 'scope', name: 'scope', orderable: false, searchable: false},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    $(document).on('click', '#deletebtn', function() {
        if (!confirm('Delete this currency?')) return;
        let route = $(this).data('route');
        $.ajax({
            url: route,
            type: 'POST',
            data: { _method: 'DELETE', _token: '{{ csrf_token() }}', id: $(this).data('id') },
            success: function() {
                $('#currency-table').DataTable().ajax.reload(null, false);
            }
        });
    });
});
</script>
@endpush
