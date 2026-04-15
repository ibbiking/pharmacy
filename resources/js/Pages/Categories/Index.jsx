import React, { useEffect, useRef } from 'react';
import { Head, Link, usePage } from '@inertiajs/inertia-react';

export default function Index({ title, permissions, routes }) {
    const { flash } = usePage().props;
    const tableRef = useRef(null);

    useEffect(() => {
        const $ = window.$;
        if (!$ || !$.fn.DataTable || !tableRef.current) {
            return undefined;
        }

        const table = $(tableRef.current).DataTable({
            processing: true,
            serverSide: true,
            ajax: routes.index,
            pageLength: 20,
            lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
            columns: [
                { data: 'name', name: 'name' },
                { data: 'created_at', name: 'created_at' },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });

        const clickHandler = function (event) {
            event.preventDefault();
            const route = this.dataset.route;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const swal = window.Swal || window.swal;

            if (!route) {
                return;
            }

            const askConfirm = swal
                ? swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                })
                : Promise.resolve({ isConfirmed: window.confirm("Are you sure? You won't be able to revert this!") });

            askConfirm.then((result) => {
                const confirmed = Boolean(result && (result.isConfirmed === true || result.value === true));
                if (!confirmed) {
                    return;
                }

                $.ajax({
                    url: route,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: csrf,
                    },
                    dataType: 'json',
                    success: (response) => {
                        table.ajax.reload(null, false);
                        if (swal) {
                            swal.fire({
                                title: 'Deleted!',
                                text: response?.message || 'Packaging deleted successfully.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false,
                            });
                        }
                    },
                    error: (xhr) => {
                        const message =
                            xhr?.responseJSON?.message ||
                            'Could not delete packaging. Please try again.';
                        if (swal) {
                            swal.fire({
                                title: 'Delete failed',
                                text: message,
                                icon: 'error',
                            });
                        } else {
                            window.alert(message);
                        }
                    },
                });
            });
        };

        $(document).on('click', '#category-table .deletebtn', clickHandler);

        return () => {
            $(document).off('click', '#category-table .deletebtn', clickHandler);
            table.destroy(true);
        };
    }, [routes.index]);

    return (
        <>
            <Head title="Packaging" />

            <div className="page-header">
                <div className="row">
                    <div className="col-sm-7 col-auto">
                        <h3 className="page-title">Packaging</h3>
                        <ul className="breadcrumb">
                            <li className="breadcrumb-item"><a href="/admin/dashboard">Dashboard</a></li>
                            <li className="breadcrumb-item active">Packaging</li>
                        </ul>
                    </div>
                    {permissions?.create && (
                        <div className="col-sm-5 col text-right">
                            <Link href={routes.create} className="btn btn-success mt-2">Add Packaging</Link>
                        </div>
                    )}
                </div>
            </div>

            {(flash?.success || flash?.message) && (
                <div className="alert alert-success">{flash.success || flash.message}</div>
            )}

            <div className="row">
                <div className="col-sm-12">
                    <div className="card">
                        <div className="card-body">
                            <div className="table-responsive">
                                <table
                                    id="category-table"
                                    ref={tableRef}
                                    className="datatable table table-striped table-bordered table-hover table-center mb-0"
                                >
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Created Date</th>
                                            <th className="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody />
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
