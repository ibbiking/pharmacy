@extends('admin.layouts.app')

<x-assets.datatables />

@push('page-css')
@endpush

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

@push('page-header')
<div class="col-sm-7 col-auto">
    <h3 class="page-title">Generic Products</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Generic System</li>
    </ul>
</div>
<div class="col-sm-5 col">
    @if(auth()->user()->hasRole('super-admin'))
    <a href="{{ route('generic_products.syncAll') }}" class="btn btn-warning float-right mt-2 ml-2"><i class="fas fa-sync"></i> Sync All Products</a>
    @endif
    <a href="{{ route('generic_products.suggest') }}" class="btn btn-primary float-right mt-2 ml-2">Suggest New</a>
    @if(!auth()->user()->hasRole('super-admin') || session()->has('impersonate_business_id'))
    <button id="bulk-import-btn" class="btn btn-success float-right mt-2"><i class="fas fa-download"></i> Import Selected Items</button>
    @endif
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="generic-product-table" class="datatable table table-striped table-bordered table-hover table-center mb-0">
                        <thead>
                            <tr style="border:1px solid black;">
                                <th>
                                    @if(!auth()->user()->hasRole('super-admin') || session()->has('impersonate_business_id'))
                                    <input type="checkbox" id="select-all">
                                    @endif
                                </th>
                                <th>Name</th>
                                <th>Strength</th>
                                <th>Type</th>
                                <th>Company</th>
                                <th>Farmula</th>
                                <th>Status</th>
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

<!-- Generic Product Details Modal -->
<div class="modal fade" id="genericDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content" id="genericDetailsContent">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="mt-2">Loading product details...</div>
            </div>
        </div>
    </div>
</div>

<!-- Full Page Import Loader -->
<div id="full-page-import-loader" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); z-index:99999; text-align:center;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">
        <div class="spinner-border text-primary" style="width: 4rem; height: 4rem; border-width: 0.3em;" role="status"></div>
        <h3 class="mt-4 text-primary font-weight-bold">Queueing Import Request...</h3>
        <p class="text-muted">Selected items will import automatically in the next scheduled run.</p>
    </div>
</div>
@endsection

@push('page-js')
<script>
    $(document).ready(function() {
        @if(session('auto_open_generic_wizard'))
            let autoOpenId = {{ session('auto_open_generic_wizard') }};
            $('#setupWizardModal').modal('show');
            $('#setupWizardContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading Setup Wizard...</div></div>');

            $.ajax({
                url: "{{ url('admin/generic-products') }}/" + autoOpenId + "/setup-wizard",
                type: 'GET',
                success: function(res) {
                    $('#setupWizardContent').html(res);
                },
                error: function(xhr) {
                    $('#setupWizardContent').html('<div class="modal-body text-danger p-5">Failed to load setup wizard. ' + xhr.status + '</div>');
                }
            });
        @endif

        var table = $('#generic-product-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('generic_products.index') }}",
            columns: [
                {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},
                {data: 'product_name', name: 'product_name'},
                {data: 'strength', name: 'strength', orderable: false, searchable: true},
                {data: 'type', name: 'type', orderable: false, searchable: true},
                {data: 'company', name: 'company', orderable: false, searchable: true},
                {data: 'farmula', name: 'farmula', orderable: false, searchable: true},
                {data: 'status_badge', name: 'status', orderable: false, searchable: false},
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

        // Bulk Import Action
        $('#bulk-import-btn').on('click', function() {
            var selectedIds = [];
            $('.generic-checkbox:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if(selectedIds.length === 0) {
                alert('Please select at least one product to import.');
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Queueing...');
            $('#full-page-import-loader').fadeIn();

            $.ajax({
                url: "{{ route('generic_products.bulkImport') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    product_ids: selectedIds
                },
                success: function(response) {
                    $('#full-page-import-loader').fadeOut();
                    if(response.success) {
                        alert(response.success);
                        $('#select-all').prop('checked', false);
                        table.ajax.reload();
                    } else if(response.error) {
                        alert(response.error);
                    }
                    btn.prop('disabled', false).html('<i class="fas fa-download"></i> Import Selected Items');
                },
                error: function(err) {
                    $('#full-page-import-loader').fadeOut();
                    alert('An error occurred during bulk import.');
                    btn.prop('disabled', false).html('<i class="fas fa-download"></i> Import Selected Items');
                }
            });
        });

        $(document).on('click', '.import-generic', function() {
            var id = $(this).data('id');
            var btn = $(this);
            btn.prop('disabled', true).text('Importing...');
            
            $.ajax({
                url: "{{ route('generic_products.import') }}",
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    product_id: id
                },
                success: function(response) {
                    if(response.success) {
                        alert(response.success);
                        table.ajax.reload();
                    } else if(response.error) {
                        alert(response.error);
                        btn.prop('disabled', false).html('<i class="fas fa-download"></i> Import to Business');
                    }
                },
                error: function(err) {
                    alert('An error occurred during import.');
                    btn.prop('disabled', false).html('<i class="fas fa-download"></i> Import to Business');
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

        $(document).on('click', '.btn-view-generic-details', function() {
            let productId = $(this).data('id');
            $('#genericDetailsModal').modal('show');
            $('#genericDetailsContent').html('<div class="modal-body text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading product details...</div></div>');

            $.ajax({
                url: "{{ url('admin/generic-products') }}/" + productId + "/details",
                type: 'GET',
                success: function(res) {
                    $('#genericDetailsContent').html(res);
                },
                error: function(xhr) {
                    $('#genericDetailsContent').html('<div class="modal-body text-danger p-5">Failed to load product details. ' + xhr.status + '</div>');
                }
            });
        });
    });
</script>
@endpush
