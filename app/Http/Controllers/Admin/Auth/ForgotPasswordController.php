<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PlatformMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

/**
 * Forgot password (Roadmap 5.0): email a 6-digit code → enter the code → new password.
 * Never reveals whether an email has an account; a code dies after 5 wrong tries or when it expires.
 */
class ForgotPasswordController extends Controller
{
    public const MAX_CODE_ATTEMPTS = 5;
    private const GENERIC = 'If this email has an account, we sent it a 6-digit code.';

    public function index(Request $request)
    {
        return Inertia::render('Auth/ForgotPassword', ['email' => (string) $request->query('email', '')]);
    }

    public function requestEmail(Request $request)
    {
        $email = Str::lower($request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        if (User::where('email', $email)->exists()) {
            if ($wait = $this->cooldown($email)) {
                return redirect()->route('password.verify.form', ['email' => $email])
                    ->with('error', "A code was sent a moment ago. You can ask for another in {$wait} seconds.");
            }
            if (! $this->sendCode($email)) {
                return back()->with('error', 'We couldn’t send the email right now. Please try again in a minute.');
            }
        }

        return redirect()->route('password.verify.form', ['email' => $email])->with('success', self::GENERIC);
    }

    public function showVerifyForm(Request $request)
    {
        $email = Str::lower((string) $request->query('email', ''));
        if ($email === '') {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/VerifyCode', [
            'email' => $email,
            'resendIn' => $this->cooldown($email),
            'expiryMinutes' => $this->expiryMinutes(),
        ]);
    }

    public function verifyCode(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ], ['code.digits' => 'The code has 6 digits.']);
        $email = Str::lower($data['email']);
        $row = $this->latest($email);

        $fail = fn (string $msg) => back()->withErrors(['code' => $msg]);
        if (! $row) {
            return $fail('This code is not valid. Ask for a new one.');
        }
        if (Carbon::parse($row->created_at)->addMinutes($this->expiryMinutes())->isPast()) {
            return $fail('This code has expired. Ask for a new one.');
        }
        if (! Hash::check($data['code'], $row->token)) {
            $attempts = $row->attempts + 1;
            DB::table('password_resets')->where('email', $email)->whereNull('deleted_at')->update(
                $attempts >= self::MAX_CODE_ATTEMPTS ? ['attempts' => $attempts, 'deleted_at' => now()] : ['attempts' => $attempts]
            );

            return $fail($attempts >= self::MAX_CODE_ATTEMPTS
                ? 'Too many wrong codes. This code is cancelled: ask for a new one.'
                : 'That code is not right. '.(self::MAX_CODE_ATTEMPTS - $attempts).' tries left.');
        }

        session(['password_reset_verified_email' => $email, 'password_reset_verified_at' => now()->timestamp]);

        return redirect()->route('password.reset.form');
    }

    public function resendCode(Request $request)
    {
        $email = Str::lower($request->validate(['email' => ['required', 'email']])['email']);

        if (User::where('email', $email)->exists()) {
            if ($wait = $this->cooldown($email)) {
                return back()->with('error', "Please wait {$wait} seconds before asking for another code.");
            }
            if (! $this->sendCode($email)) {
                return back()->with('error', 'We couldn’t send the email right now. Please try again in a minute.');
            }
        }

        return back()->with('success', 'If this email has an account, a new code is on its way. Older codes no longer work.');
    }

    private function latest(string $email): ?object
    {
        return DB::table('password_resets')->where('email', $email)->whereNull('deleted_at')->orderByDesc('created_at')->first();
    }

    private function cooldown(string $email): int
    {
        $row = $this->latest($email);
        if (! $row) {
            return 0;
        }
        $elapsed = (int) Carbon::parse($row->created_at)->diffInSeconds(now(), true);

        return max(0, $this->cooldownSeconds() - $elapsed);
    }

    private function sendCode(string $email): bool
    {
        $code = (string) random_int(100000, 999999);

        try {
            DB::transaction(function () use ($email, $code) {
                DB::table('password_resets')->where('email', $email)->whereNull('deleted_at')->update(['deleted_at' => now()]);
                DB::table('password_resets')->insert(['email' => $email, 'token' => Hash::make($code), 'attempts' => 0, 'created_at' => now(), 'deleted_at' => null]);
            });
            Mail::to($email)->send(new PlatformMessage(
                mailSubject: 'Your password reset code',
                title: 'Reset your password',
                lines: ['Use this code to choose a new password. It expires in '.$this->expiryMinutes().' minutes and works once.'],
                summary: [['Your code', $code]],
                outro: 'If you didn’t ask for this, ignore this email: your password stays the same.',
                preheader: "Your code: {$code}",
            ));

            return true;
        } catch (Throwable $e) {
            Log::warning('Password reset email could not be sent', ['email' => $email, 'error' => $e->getMessage()]);
            DB::table('password_resets')->where('email', $email)->whereNull('deleted_at')->update(['deleted_at' => now()]);

            return false;
        }
    }

    private function expiryMinutes(): int
    {
        return (int) config('auth.password_reset_code.expiry_minutes', 15);
    }

    private function cooldownSeconds(): int
    {
        return (int) config('auth.password_reset_code.resend_cooldown_seconds', 60);
    }
}
