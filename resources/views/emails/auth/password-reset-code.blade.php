<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Code</title>
</head>
<body>
    <h2>Password Reset Request</h2>
    <p>We received a request to reset your password.</p>
    <p>Use this verification code:</p>
    <p style="font-size: 24px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>
    <p>This code will expire in {{ $expiryMinutes }} minutes.</p>
    <p>If you did not request a password reset, please ignore this email.</p>
    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>
