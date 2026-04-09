@extends('admin.layouts.plain')

@section('content')
<h1>Pharmacy System</h1>
<p class="account-subtitle">Complete Registration</p>
@if ($errors->any())
    @foreach ($errors->all() as $error)
        <x-alerts.danger :error="$error" />
    @endforeach
@endif
<!-- Form -->
<form action="{{route('register.complete')}}" method="post">
	@csrf
    <input type="hidden" name="token" value="{{ $signupRequest->token }}">
	<div class="form-group">
		<input class="form-control" name="name" type="text" value="{{ $signupRequest->name }}" readonly>
	</div>
	<div class="form-group">
		<input class="form-control" name="email" type="email" value="{{ $signupRequest->email }}" readonly>
	</div>
	<div class="form-group">
		<input class="form-control" name="password" type="password" placeholder="Password" required>
	</div>
	<div class="form-group">
		<input class="form-control" name="password_confirmation" type="password" placeholder="Confirm Password" required>
	</div>
	<div class="form-group mb-0">
		<button class="btn btn-primary btn-block" type="submit">Complete Setup</button>
	</div>
</form>
<!-- /Form -->
@endsection
