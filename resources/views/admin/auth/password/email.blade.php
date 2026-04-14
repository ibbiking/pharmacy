@extends('admin.layouts.plain')

@section('content')
<h1>Forgot Password?</h1>
<p class="account-subtitle">Enter your email to get a password reset code</p>
@if (session('status'))
<x-alerts.success :message="session('status')" />
@endif
<!-- Form -->
<form action="{{route('password.email')}}" method="post">
	@csrf
	<div class="form-group">
		<input class="form-control" name="email" type="text" placeholder="Email">
	</div>
	<div class="form-group mb-0">
		<button class="btn btn-primary btn-block" type="submit">Submit</button>
	</div>
</form>
<!-- /Form -->

<div class="text-center dont-have">Remember your password? <a href="{{route('login')}}">Login</a></div>
@endsection