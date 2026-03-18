@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
	<h3 class="page-title">Invoices</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">Invoices</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-12">
	
		<div class="card">
			<div class="card-body">
				<div class="table-responsive">
					<table id="invoice-table" class="datatable table table-hover table-center mb-0">
						<thead>
							<tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Grand Total</th>
                                <th>Cash Received</th>
                                <th>Change</th>
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
        var table = $('#invoice-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{route('invoices.index')}}",
            columns: [
                {data: 'invoice_no', name: 'invoice_no'},
                {data: 'date', name: 'created_at'},
                {data: 'grand_total', name: 'grand_total'},
                {data: 'cash_received', name: 'cash_received'},
                {data: 'change_return', name: 'change_return'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
        
    });
</script> 
@endpush