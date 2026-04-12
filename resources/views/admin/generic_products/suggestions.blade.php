@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-header')
<div class="col-sm-7 col-auto">
    <h3 class="page-title">Suggested Products</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Suggested Products</li>
    </ul>
</div>
<div class="col-sm-5 col">
    <button id="bulk-approve-btn" class="btn btn-success float-right mt-2"><i class="fas fa-check-double"></i> Approve Selected Items</button>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="suggestions-product-table" class="datatable table table-striped table-bordered table-hover table-center mb-0">
                        <thead>
                            <tr style="border:1px solid black;">
                                <th><input type="checkbox" id="select-all"></th>
                                <th>Name</th>
                                <th>Strength</th>
                                <th>Type</th>
                                <th>Company</th>
                                <th>Farmula</th>
                                <th>Action</th>
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

<!-- Setup Wizard Modal Slideout -->
<div class="modal fade right" id="setupWizardModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-slideout" role="document">
        <div class="modal-content" id="setupWizardContent">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2">Loading Setup Wizard...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-css')
<style>
.modal.right .modal-dialog-slideout {
    position: fixed;
    margin: auto;
    width: 90%;
    max-width: 900px;
    height: 100%;
    transform: translate3d(100%, 0, 0);
    transition: transform 0.3s ease-out;
}
.modal.right.show .modal-dialog-slideout {
    transform: translate3d(0, 0, 0);
    right: 0;
}
.modal.right .modal-content {
    height: 100%;
    overflow-y: auto;
    border-radius: 0;
    border: none;
}
</style>
@endpush

@push('page-js')
<script>
    $(document).ready(function() {
        var table = $('#suggestions-product-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('generic_products.suggestions') }}",
            columns: [
                {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},
                {data: 'product_name', name: 'product_name'},
                {data: 'strength', name: 'strength', orderable: false, searchable: true},
                {data: 'type', name: 'type', orderable: false, searchable: true},
                {data: 'company', name: 'company', orderable: false, searchable: true},
                {data: 'farmula', name: 'farmula', orderable: false, searchable: true},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        // Select All checkboxes behavior
        $('#select-all').on('click', function(){
            var isChecked = $(this).prop('checked');
            $('.generic-checkbox').prop('checked', isChecked);
        });

        $(document).on('change', '.generic-checkbox', function(){
            if($('.generic-checkbox:checked').length == $('.generic-checkbox').length) {
                $('#select-all').prop('checked', true);
            } else {
                $('#select-all').prop('checked', false);
            }
        });

        // Bulk Approve Action
        $('#bulk-approve-btn').on('click', function() {
            var selectedIds = [];
            $('.generic-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if(selectedIds.length === 0) {
                alert('Please select at least one product to approve.');
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

            $.ajax({
                url: "{{ route('generic_products.bulkApprove') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    ids: selectedIds
                },
                success: function(response) {
                    if(response.success) {
                        alert(response.message);
                        $('#select-all').prop('checked', false);
                        table.ajax.reload();
                    } else if(response.error) {
                        alert(response.error);
                    }
                    btn.prop('disabled', false).html('<i class="fas fa-check-double"></i> Approve Selected Items');
                },
                error: function(err) {
                    alert('An error occurred during bulk approval.');
                    btn.prop('disabled', false).html('<i class="fas fa-check-double"></i> Approve Selected Items');
                }
            });
        });

        $(document).on('click', '.btn-setup-wizard', function() {
            let productId = $(this).data('id');
            $('#setupWizardModal').modal('show');
            $('#setupWizardContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading Setup Wizard...</div></div>');

            $.ajax({
                url: "{{ url('admin/generic-products') }}/" + productId + "/setup-wizard",
                type: 'GET',
                success: function(res) {
                    $('#setupWizardContent').html(res);
                },
                error: function(xhr) {
                    $('#setupWizardContent').html('<div class="modal-body text-danger p-5">Failed to load setup wizard. ' + xhr.status + '</div>');
                }
            });
        });

    });
</script>
@endpush
