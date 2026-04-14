<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class ForgotPasswordController extends Controller
{
    public function index()
    {
        $title = 'forgot password';
        return view('admin.auth.password.email', compact('title'));
    }

    public function requestEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;
        $genericMessage = 'If your email exists in our system, we sent a reset code.';
        $userExists = User::where('email', $email)->exists();

        if ($userExists) {
            $existingReset = $this->getLatestResetRow($email);
            if ($existingReset) {
                $secondsSinceLastCode = Carbon::parse($existingReset->created_at)->diffInSeconds(now());
                if ($secondsSinceLastCode < $this->resendCooldownSeconds()) {
                    $waitSeconds = $this->resendCooldownSeconds() - $secondsSinceLastCode;
                    return back()->withErrors([
                        'email' => "Please wait {$waitSeconds} seconds before requesting another code.",
                    ]);
                }
            }

            $this->sendAndStoreResetCode($email);
        }

        return redirect()
            ->route('password.verify.form', ['email' => $email])
            ->with('status', $genericMessage);
    }

    public function showVerifyForm(Request $request)
    {
        $email = $request->query('email');
        $title = 'verify reset code';
        $remainingSeconds = $this->getRemainingCooldownSeconds($email);

        return view('admin.auth.password.verify-code', [
            'title' => $title,
            'email' => $email,
            'remainingSeconds' => $remainingSeconds,
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
        ]);

        $resetRow = DB::table('password_resets')
            ->where('email', $request->email)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();

        if (!$resetRow) {
            return back()->withErrors(['code' => 'Invalid reset code.']);
        }

        if (Carbon::parse($resetRow->created_at)->addMinutes($this->codeExpiryMinutes())->isPast()) {
            return back()->withErrors(['code' => 'Reset code has expired. Please request a new one.']);
        }

        if (!Hash::check($request->code, $resetRow->token)) {
            return back()->withErrors(['code' => 'Invalid reset code.']);
        }

        session(['password_reset_verified_email' => $request->email]);

        return redirect()->route('password.reset.form');
    }

    public function resendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;
        $genericMessage = 'If your email exists in our system, a new reset code has been sent.';
        $userExists = User::where('email', $email)->exists();

        if ($userExists) {
            $remainingSeconds = $this->getRemainingCooldownSeconds($email);
            if ($remainingSeconds > 0) {
                return back()->withErrors([
                    'code' => "Please wait {$remainingSeconds} seconds before resending the code.",
                ]);
            }

            $this->sendAndStoreResetCode($email);
        }

        return redirect()
            ->route('password.verify.form', ['email' => $email])
            ->with('status', $genericMessage);
    }

    private function getLatestResetRow(string $email): ?object
    {
        return DB::table('password_resets')
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function getRemainingCooldownSeconds(?string $email): int
    {
        if (!$email) {
            return 0;
        }

        $latestReset = $this->getLatestResetRow($email);
        if (!$latestReset) {
            return 0;
        }

        $secondsSinceLastCode = Carbon::parse($latestReset->created_at)->diffInSeconds(now());
        if ($secondsSinceLastCode >= $this->resendCooldownSeconds()) {
            return 0;
        }

        return $this->resendCooldownSeconds() - $secondsSinceLastCode;
    }

    private function sendAndStoreResetCode(string $email): void
    {
        $code = (string) random_int(100000, 999999);

        DB::table('password_resets')
            ->where('email', $email)
            ->update(['deleted_at' => now()]);

        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => Hash::make($code),
            'created_at' => now(),
            'deleted_at' => null,
        ]);

        Mail::send('emails.auth.password-reset-code', [
            'code' => $code,
            'expiryMinutes' => $this->codeExpiryMinutes(),
        ], function ($message) use ($email) {
            $message->to($email)->subject('Password Reset Code');
        });
    }

    private function codeExpiryMinutes(): int
    {
        return (int) config('auth.password_reset_code.expiry_minutes', 15);
    }

    private function resendCooldownSeconds(): int
    {
        return (int) config('auth.password_reset_code.resend_cooldown_seconds', 60);
    }
}
