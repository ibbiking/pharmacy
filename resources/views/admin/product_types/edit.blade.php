@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Edit Product Type</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Edit Product Type</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('product-types.update', $productType->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label>Name<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="name" value="{{ old('name', $productType->name) }}">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description">{{ old('description', $productType->description) }}</textarea>
                    </div>
                    <button class="btn btn-success" type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection