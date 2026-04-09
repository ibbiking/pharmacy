<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email</title>
</head>
<body>
    <h2>Hello {{ $signupRequest->name }},</h2>
    <p>Thank you for signing up. Please click the link below to verify your email address and complete your registration.</p>
    <p>
        <a href="{{ route('verification.verify', ['token' => $signupRequest->token]) }}" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Verify Email
        </a>
    </p>
    <p>If you did not request this, please ignore this email.</p>
    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>
