<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/** Step 3 of "forgot password": choose a new password (only after the emailed code was verified). */
class ResetPasswordController extends Controller
{
    /** The verified code is good for this long. */
    private const WINDOW_MINUTES = 15;

    public function index()
    {
        if (! $email = $this->verifiedEmail()) {
            return redirect()->route('password.request')->with('error', 'Please start again: ask for a new code.');
        }

        return Inertia::render('Auth/ResetPassword', ['email' => $email]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', 'max:200', Password::min(8)->letters()->numbers()],
        ]);
        $email = $this->verifiedEmail();
        $user = $email ? User::where('email', $email)->first() : null;
        if (! $user) {
            session()->forget(['password_reset_verified_email', 'password_reset_verified_at']);

            return redirect()->route('password.request')->with('error', 'Your reset session has ended. Please ask for a new code.');
        }

        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        DB::table('password_resets')->where('email', $email)->whereNull('deleted_at')->update(['deleted_at' => now()]);
        session()->forget(['password_reset_verified_email', 'password_reset_verified_at']);

        return redirect()->route('login', ['email' => $email])->with('status', 'Password changed. Sign in with your new password.');
    }

    private function verifiedEmail(): ?string
    {
        $email = session('password_reset_verified_email');
        $at = (int) session('password_reset_verified_at', 0);

        return $email && $at >= now()->subMinutes(self::WINDOW_MINUTES)->timestamp ? $email : null;
    }
}
