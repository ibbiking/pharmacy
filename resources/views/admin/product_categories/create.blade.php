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
                <form method="post" action="{{route('product-categories.store')}}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">

                    <div class="form-group">
                        <label>Parent Category</label>
                        <select class="form-control" id="parent" name="parent_category_id" required>
                            <option value="">-- Select Parent --</option>
                            @foreach($parentCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_category_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label>Child Category</label>
                        <select class="form-control" id="child" name="child_category_id" disabled required>
                            <option value="">-- Select Child --</option>
                            @foreach($childCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('child_category_id') <span class="text-danger">{{ $message }}</span> @enderror
                    </div>

                    <button class="btn btn-success" type="submit">Save</button>
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
                            <th>Parent Category</th>
                            <th>Child Category</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($relations as $index => $relation)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $relation->parentCategory->name ?? '-' }}</td>
                            <td>{{ $relation->childCategory->name ?? '-' }}</td>
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
document.getElementById('parent').addEventListener('change', function(){
    let parentVal = this.value;
    let child = document.getElementById('child');
    child.disabled = !parentVal;

    Array.from(child.options).forEach(opt => {
        opt.hidden = (opt.value === parentVal && opt.value !== "");
    });
});
</script>
@endpush