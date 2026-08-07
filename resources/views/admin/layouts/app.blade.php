<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <!-- csrf token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ucfirst(AppSettings::get('app_name', 'App'))}} - {{ucfirst($title ?? '')}}</title>
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="{{!empty(AppSettings::get('favicon')) ? asset('storage/'.AppSettings::get('favicon')) : asset('assets/img/favicon.png')}}">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{asset('assets/css/bootstrap.min.css')}}">
    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="{{asset('assets/plugins/fontawesome/css/fontawesome.min.css')}}">
    <!-- Feathericon CSS -->
    <link rel="stylesheet" href="{{asset('assets/css/feathericon.min.css')}}">

    <link rel="stylesheet" href="{{asset('assets/css/icons.min.css')}}">
    <!-- Snackbar CSS -->
	<link rel="stylesheet" href="{{asset('assets/plugins/snackbar/snackbar.min.css')}}">
    <!-- Sweet Alert css -->
    <link rel="stylesheet" href="{{asset('assets/plugins/sweetalert2/sweetalert2.min.css')}}">
    <!-- Snackbar Css -->
    <link rel="stylesheet" href="{{asset('assets/plugins/snackbar/snackbar.min.css')}}">
    <!-- Select2 Css -->
    <link rel="stylesheet" href="{{asset('assets/plugins/select2/css/select2.min.css')}}">
    <!-- Main CSS -->
    <link rel="stylesheet" href="{{asset('assets/css/style.css')}}">
    <!-- Page CSS -->
    @stack('page-css')
    
    <style>
        .dataTables_length {
            margin-bottom: 15px !important;
        }
        .dt-buttons {
            margin-bottom: 15px !important;
        }

        /* Global Table & Card Beautification */
        .card {
            border-radius: 12px !important;
            border: 1px solid #e3e8ee !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03) !important;
        }
        .card-header {
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e3e8ee !important;
            padding: 1rem 1.25rem !important;
        }
        
        /* Modern DataTable Styling */
        .table-responsive {
            border-radius: 10px;
        }
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            width: 100% !important;
        }
        table.dataTable thead th {
            background-color: #f1f5f9 !important;
            color: #475569 !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            font-size: 0.8rem !important;
            letter-spacing: 0.5px !important;
            border-bottom: 2px solid #cbd5e1 !important;
            padding: 12px 16px !important;
        }
        table.dataTable tbody td {
            padding: 12px 16px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            font-size: 0.92rem;
            color: #334155;
        }
        table.dataTable tbody tr {
            transition: all 0.2s ease-in-out;
        }
        table.dataTable tbody tr:hover {
            background-color: #f8fafc !important;
            box-shadow: inset 3px 0 0 #007bff;
        }

        /* DataTable Search & Controls */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 20px !important;
            padding: 5px 12px !important;
            margin-left: 5px !important;
            border: 1px solid #cbd5e1 !important;
            outline: none;
            transition: all 0.2s ease;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #007bff !important;
            box-shadow: 0 0 8px rgba(0, 123, 255, 0.2) !important;
        }
        .dataTables_wrapper .dataTables_length label {
            display: inline-flex !important;
            align-items: center !important;
            font-weight: 600 !important;
            color: #475569 !important;
            margin-bottom: 15px !important;
        }
        .dataTables_wrapper .dataTables_length select {
            border-radius: 15px !important;
            padding: 4px 18px 4px 9px !important;
            margin: 0 4px !important;
            border: 1px solid #cbd5e1 !important;
            outline: none !important;
            font-weight: 600 !important;
            color: #334155 !important;
            display: inline-block !important;
            min-width: 58px !important;
            cursor: pointer;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 20px !important;
            padding: 4px 12px !important;
            margin: 0 2px !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, #007bff, #0056b3) !important;
            color: #fff !important;
            border: none !important;
            box-shadow: 0 2px 6px rgba(0, 123, 255, 0.3) !important;
        }
    </style>

    <!--[if lt IE 9]>
        <script src="assets/js/html5shiv.min.js"></script>
        <script src="assets/js/respond.min.js"></script>
    <![endif]-->
</head>
<body>

    <!-- Main Wrapper -->
    <div class="main-wrapper">

        <!-- Header -->
        @include('admin.includes.header')
        <!-- /Header -->

        <!-- Sidebar -->
        @include('admin.includes.sidebar')
        <!-- /Sidebar -->

        <!-- Page Wrapper -->
        <div class="page-wrapper">

            <div class="content container-fluid">

                <!-- Page Header -->
                <div class="page-header">
                    <div class="row">
                        @stack('page-header')
                    </div>
                </div>
                <!-- /Page Header -->
                @if ($errors->any())
                    @foreach ($errors->all() as $error)
                        <x-alerts.danger :error="$error" />
                    @endforeach
                @endif

                @yield('content')
                <!-- add sales modal-->
                <x-modals.add-sale />
                 <!-- / add sales modal -->
            </div>
        </div>
        <!-- /Page Wrapper -->

    </div>
    <!-- /Main Wrapper -->

    
<!-- jQuery -->
<script src="{{asset('assets/plugins/jquery/jquery.min.js')}}"></script>

<!-- Bootstrap Core JS -->
<script src="{{asset('assets/js/popper.min.js')}}"></script>
<script src="{{asset('assets/js/bootstrap.min.js')}}"></script>
<script src="{{asset('assets/plugins/slimscroll/jquery.slimscroll.min.js')}}"></script>
<!-- Sweet Alert Js -->
<script src="{{asset('assets/plugins/sweetalert2/sweetalert2.min.js')}}"></script>
<!-- Snackbar Js -->
<script src="{{asset('assets/plugins/snackbar/snackbar.min.js')}}"></script>
<!-- Select2 JS -->
<script src="{{asset('assets/plugins/select2/js/select2.min.js')}}"></script>
<!-- Custom JS -->
<script src="{{asset('assets/js/script.js')}}"></script>
<script>
    $(document).ready(function(){
        $.extend(true, $.fn.dataTable.defaults, { 
            pageLength: 20,
            lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]]
        });
        $('body').on('click','#deletebtn',function(){
            var id = $(this).data('id');
            var route = $(this).data('route');
            swal.queue([
                {
                    title: "Are you sure?",
                    text: "You won't be able to revert this!",
                    type: "warning",
                    showCancelButton: !0,
                    confirmButtonText: '<i class="fe fe-trash mr-1"></i> Delete!',
                    cancelButtonText: '<i class="fa fa-times mr-1"></i> Cancel!',
                    confirmButtonClass: "btn btn-success mt-2",
                    cancelButtonClass: "btn btn-danger ml-2 mt-2",
                    buttonsStyling: !1,
                    preConfirm: function(){
                        return new Promise(function(){
                            $.ajax({
                                url: route,
                                type: "DELETE",
                                data: {"id": id},
                                success: function(){
                                    swal.insertQueueStep(
                                        Swal.fire({
                                            title: "Deleted!",
                                            text: "Resource has been deleted.",
                                            type: "success",
                                            showConfirmButton: !1,
                                            timer: 1500,
                                        })
                                    )
                                    $('.datatable').DataTable().ajax.reload();
                                }
                            })

                        })
                    }
                }
            ]).catch(swal.noop);
        }); 
    });
    @if(Session::has('message'))
        var type = "{{ Session::get('alert-type', 'info') }}";
        switch(type){
            case 'info':
                Snackbar.show({
                    text: "{{ Session::get('message') }}",
                    actionTextColor: '#fff',
                    backgroundColor: '#2196f3'
                });
                break;

            case 'warning':
                Snackbar.show({
                    text: "{{ Session::get('message') }}",
                    pos: 'top-right',
                    actionTextColor: '#fff',
                    backgroundColor: '#e2a03f'
                });
                break;

            case 'success':
                Snackbar.show({
                    text: "{{ Session::get('message') }}",
                    pos: 'top-right',
                    actionTextColor: '#fff',
                    backgroundColor: '#8dbf42'
                });
                break;

            case 'danger':
                Snackbar.show({
                    text: "{{ Session::get('message') }}",
                    pos: 'top-right',
                    actionTextColor: '#fff',
                    backgroundColor: '#e7515a'
                });
                break;
        }
    @endif
</script>
<!-- Page JS -->
@stack('page-js')
</body>
</html>