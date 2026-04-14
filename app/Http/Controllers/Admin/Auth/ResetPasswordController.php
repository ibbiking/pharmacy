<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function index()
    {
        $verifiedEmail = session('password_reset_verified_email');
        if (!$verifiedEmail) {
            return redirect()->route('password.request');
        }

        $title = 'reset password';
        return view('admin.auth.password.reset', [
            'title' => $title,
            'email' => $verifiedEmail,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $verifiedEmail = session('password_reset_verified_email');
        if (!$verifiedEmail || $verifiedEmail !== $request->email) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Your reset session is invalid. Please start again.']);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'No account found with this email address.']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_resets')
            ->where('email', $request->email)
            ->update(['deleted_at' => now()]);

        session()->forget('password_reset_verified_email');

        return redirect()->route('login')->with('status', 'Password reset successfully. Please log in.');
    }
}
