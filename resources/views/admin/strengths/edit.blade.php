@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
    <h3 class="page-title">Edit Strength</h3>
    <ul class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{route('dashboard')}}">Dashboard</a></li>
        <li class="breadcrumb-item active">Edit Strength</li>
    </ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('strengths.update', $strength->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label>Name<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="name" value="{{ old('name', $strength->name) }}">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description">{{ old('description', $strength->description) }}</textarea>
                    </div>
                    <button class="btn btn-success" type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection