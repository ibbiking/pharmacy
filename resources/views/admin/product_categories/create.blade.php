@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Add Product Category Relation ({{ $product->product_name }})</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Add Relation</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ route('product-categories.store') }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    <div class="form-group">
                        <label>Packaging Category</label>
                        <select class="select2 form-control" id="parent" name="parent_category_id" {{ $relations->count() ? 'disabled' : '' }}>
                            <option value="">-- Select Packaging --</option>
                            @foreach($parentCategories as $category)
                            <option value="{{ $category->id }}">
                                {{ $category->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('parent_category_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    @if(!$relations->count())
                    <button class="btn btn-success" type="submit">Save</button>
                    @else
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> A product can only have exactly one assigned packaging relation. To change it, remove the existing relation below.
                    </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

@if($relations->count())
<div class="row mt-4">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title">Existing Relations</h4>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Packaging Category</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($relations as $index => $relation)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $relation->parentCategory->name ?? '-' }}</td>
                            <td>
                                {{-- <a href="{{ route('product-categories.edit', $relation->id) }}"
                                    class="btn btn-info btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a> --}}
                                <a href="javascript:void(0)" data-id="{{ $relation->id }}"
                                    data-route="{{ route('product-categories.destroy',$relation->id) }}"
                                    class="btn btn-danger btn-sm deletebtn">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('page-js')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
    // ======================
    // 🔹 Delete Button Logic
    // ======================
    document.querySelectorAll('.deletebtn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();

            let route = this.dataset.route;

            Swal.fire({
                title: 'Are you sure?',
                text: "This relation will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(route, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Deleted!',
                                text: 'Product category relation deleted successfully.',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = data.redirect;
                            });
                        }
                    });
                }
            });
        });
    });

    // Parent / Child Logic Removed because there is only one category natively now.
});
</script>
@endpush