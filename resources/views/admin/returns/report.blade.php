@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')
@endpush
@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Return Report</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Return Report</li>
    </ul>
</div>
@endpush
@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <form action="" method="GET" id="filter-form">
                    <div class="row mb-3 mt-2">
                        <div class="col-sm-12 col-md-3">
                            <label>From Date</label>
                            <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate ?? '' }}">
                        </div>
                        <div class="col-sm-12 col-md-3">
                            <label>To Date</label>
                            <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate ?? '' }}">
                        </div>
                        <div class="col-sm-12 col-md-3 mt-4">
                            <button class="btn btn-primary" type="submit">Filter</button>
                            <button class="btn btn-secondary" type="button" id="reset-btn">Reset</button>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table id="returns-report-table" class="datatable table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Return No</th>
                                <th>Invoice No.</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Total Refund</th>
                                <th>Date</th>
                                <th class="action-btn">Action</th>
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
        var table = $('#returns-report-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "{{route('reports.returns')}}",
                data: function(d) {
                    d.from_date = $('#from_date').val();
                    d.to_date = $('#to_date').val();
                }
            },
            columns: [
                {data: 'return_no', name: 'return_no'},
                {data: 'invoice_no', name: 'invoice_no'},
                {data: 'product_name', name: 'product_name'},
                {data: 'qty_returned', name: 'qty_returned'},
                {data: 'total_refund', name: 'total_refund'},
                {data: 'date', name: 'date'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        $('#filter-form').on('submit', function(e) {
            e.preventDefault();
            table.draw();
        });

        $('#reset-btn').on('click', function() {
            $('#from_date').val('{{ $fromDate ?? '' }}');
            $('#to_date').val('{{ $toDate ?? '' }}');
            table.draw();
        });
    });

    function printReturnReceipt(url) {
        window.open(url, 'PrintReceipt', 'width=800,height=600');
    }
</script>
@endpush
