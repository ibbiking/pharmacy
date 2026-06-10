@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">Duplicate Contacts: {{ $contact_list->name }}</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item"><a href="{{route('bec.contact-lists.index')}}">Contact Lists</a></li>
		<li class="breadcrumb-item active">Duplicates</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Duplicate Email Entries (Not Imported)</h4>
                <p class="text-muted">These emails already exist in other lists or appeared multiple times in your file.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>First Appearance</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($duplicates as $duplicate)
                            <tr>
                                <td>{{ $duplicate->email }}</td>
                                <td>{{ $duplicate->created_at->format('M d, Y H:i') }}</td>
                                <td><span class="badge badge-warning">Already Exists</span></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center">No duplicates found.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
