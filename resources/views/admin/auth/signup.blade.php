@extends('admin.layouts.plain')

@section('content')
<h1>Pharmacy System</h1>
<p class="account-subtitle">Sign Up</p>
@if (session('success'))
<x-alerts.success :message="session('success')" />
@endif
@if ($errors->any())
    @foreach ($errors->all() as $error)
        <x-alerts.danger :error="$error" />
    @endforeach
@endif
<!-- Form -->
<form action="{{route('signup')}}" method="post">
	@csrf
	<div class="form-group">
		<input class="form-control" name="name" type="text" placeholder="Full Name" value="{{ old('name') }}" required>
	</div>
	<div class="form-group">
		<input class="form-control" name="email" type="email" placeholder="Email Address" value="{{ old('email') }}" required>
	</div>
	<div class="form-group mb-0">
		<button class="btn btn-primary btn-block" type="submit">Sign Up</button>
	</div>
</form>
<!-- /Form -->

<div class="text-center dont-have">Already have an account? <a href="{{route('login')}}">Login</a></div>
@endsection
