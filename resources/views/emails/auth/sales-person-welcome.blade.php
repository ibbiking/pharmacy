<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Person Account</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>Welcome to {{ config('app.name') }}</h2>

    <p>Hello {{ $user->name }},</p>

    <p>Your sales person account has been created. Please use the following credentials to sign in:</p>

    <p>
        <strong>Username:</strong> {{ $user->email }}<br>
        <strong>Name:</strong> {{ $user->name }}<br>
        <strong>Temporary Password:</strong> {{ $plainPassword }}
    </p>

    <p>
        <strong>Login URL:</strong>
        <a href="{{ url('/admin/login') }}">{{ url('/admin/login') }}</a>
    </p>

    <p>Please change your password after your first login from your profile settings.</p>

    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>
