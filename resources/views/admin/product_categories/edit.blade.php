@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Edit Product Category Relation</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Edit Relation</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <form method="post" action="{{route('product-categories.update',$productCategory->id)}}">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label>Packaging Category</label>
                        <select class="select2 form-control" id="parent" name="parent_category_id" required>
                            <option value="">-- Select Packaging --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ $productCategory->parent_category_id == $category->id ? 'selected':'' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="btn btn-success" type="submit">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
// Dynamic JS omitted; only one category now natively
</script>
@endpush