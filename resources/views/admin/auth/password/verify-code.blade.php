@extends('admin.layouts.plain')

@section('content')
<h1>Verify Reset Code</h1>
<p class="account-subtitle">Enter the reset code sent to your email</p>
@if (session('status'))
<x-alerts.success :message="session('status')" />
@endif
<!-- Form -->
<form action="{{route('password.verify')}}" method="post">
	@csrf
    <input type="hidden" name="email" value="{{$email}}">
	<div class="form-group">
		<input class="form-control" name="code" type="text" placeholder="6-digit code" maxlength="6">
	</div>
	<div class="form-group mb-0">
		<button class="btn btn-primary btn-block" type="submit">Verify Code</button>
	</div>
</form>
<!-- /Form -->

<form action="{{route('password.resend')}}" method="post" class="mt-3">
    @csrf
    <input type="hidden" name="email" value="{{$email}}">
	<div class="form-group mb-0">
		<button class="btn btn-outline-secondary btn-block" type="submit" id="resendCodeBtn" @if(($remainingSeconds ?? 0) > 0) disabled @endif>
            Resend Code
        </button>
	</div>
    @if(($remainingSeconds ?? 0) > 0)
    <small class="d-block text-center mt-2 text-muted" id="resendCodeText">
        You can resend in {{$remainingSeconds}} seconds.
    </small>
    @endif
</form>

<div class="text-center dont-have">Entered wrong email? <a href="{{route('password.request')}}">Start again</a></div>
@endsection

@push('page-js')
<script>
    (function () {
        var remaining = {{ (int)($remainingSeconds ?? 0) }};
        if (remaining <= 0) {
            return;
        }

        var button = document.getElementById('resendCodeBtn');
        var text = document.getElementById('resendCodeText');
        var timer = setInterval(function () {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(timer);
                button.disabled = false;
                text.textContent = 'You can resend the code now.';
                return;
            }
            text.textContent = 'You can resend in ' + remaining + ' seconds.';
        }, 1000);
    })();
</script>
@endpush
