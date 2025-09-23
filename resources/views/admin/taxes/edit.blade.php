@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Edit Tax</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Edit Tax</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('taxes.update', $tax->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="name" value="{{ old('name', $tax->name) }}">
                    </div>
                    <div class="form-group">
                        <label>Rate (%) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" step="0.01" name="rate" value="{{ old('rate', $tax->rate) }}">
                    </div>
                    <button class="btn btn-success" type="submit">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection