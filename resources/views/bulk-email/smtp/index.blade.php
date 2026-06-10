@extends('admin.layouts.app')

@push('page-header')
<div class="col-sm-12">
	<h3 class="page-title">SMTP Settings</h3>
	<ul class="breadcrumb">
		<li class="breadcrumb-item"><a href="{{route('bec.dashboard')}}">Dashboard</a></li>
		<li class="breadcrumb-item active">SMTP</li>
	</ul>
</div>
@endpush

@section('content')
<div class="row">
	<div class="col-md-8">
		<div class="card">
			<div class="card-body">
				<form action="{{ route('bec.smtp.update') }}" method="POST">
					@csrf
					<div class="row">
						<div class="col-md-9">
							<div class="form-group">
								<label>SMTP Host</label>
								<input type="text" name="host" class="form-control" value="{{ $smtp->host ?? 'smtp.gmail.com' }}" required>
							</div>
						</div>
						<div class="col-md-3">
							<div class="form-group">
								<label>Port</label>
								<input type="number" name="port" class="form-control" value="{{ $smtp->port ?? 587 }}" required>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Username (Email)</label>
								<input type="text" name="username" class="form-control" value="{{ $smtp->username ?? '' }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label>App Password</label>
								<input type="password" name="password" class="form-control" value="{{ $smtp->password ?? '' }}" required>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Encryption</label>
								<select name="encryption" class="form-control">
									<option value="tls" {{ ($smtp->encryption ?? '') == 'tls' ? 'selected' : '' }}>TLS</option>
									<option value="ssl" {{ ($smtp->encryption ?? '') == 'ssl' ? 'selected' : '' }}>SSL</option>
								</select>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>From Email</label>
								<input type="email" name="from_email" class="form-control" value="{{ $smtp->from_email ?? '' }}" required>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label>From Name</label>
								<input type="text" name="from_name" class="form-control" value="{{ $smtp->from_name ?? '' }}" required>
							</div>
						</div>
					</div>

					<div class="mt-4">
						<button type="submit" class="btn btn-primary">Save Settings</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<div class="col-md-4">
		<div class="card">
			<div class="card-header">
				<h4 class="card-title">Test Connection</h4>
			</div>
			<div class="card-body">
				<div class="form-group">
					<label>Send test email to:</label>
					<input type="email" id="test_email" class="form-control" placeholder="Enter email address">
				</div>
				<button type="button" class="btn btn-info btn-block" id="btn-test-smtp">Send Test Email</button>
				<div id="test-result" class="mt-3"></div>
			</div>
		</div>
	</div>
</div>
@endsection

@push('page-js')
<script>
$(document).ready(function() {
    $('#btn-test-smtp').on('click', function() {
        var email = $('#test_email').val();
        if(!email) return alert('Enter email');
        
        $(this).prop('disabled', true).text('Sending...');
        $('#test-result').html('');

        $.post("{{ route('bec.smtp.test') }}", {
            test_email: email,
            _token: "{{ csrf_token() }}"
        }, function(res) {
            $('#btn-test-smtp').prop('disabled', false).text('Send Test Email');
            if(res.success) {
                $('#test-result').html('<div class="alert alert-success">'+res.message+'</div>');
            } else {
                $('#test-result').html('<div class="alert alert-danger">'+res.message+'</div>');
            }
        });
    });
});
</script>
@endpush
