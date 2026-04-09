<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SignupRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;

class SignupController extends Controller
{
    public function showSignupForm()
    {
        $title = 'Sign Up';
        return view('admin.auth.signup', compact('title'));
    }

    public function processSignup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
        ]);

        $token = Str::random(64);

        $signupRequest = SignupRequest::updateOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'token' => $token,
                'expires_at' => now()->addHours(24),
            ]
        );

        Mail::to($request->email)->send(new VerifyEmail($signupRequest));

        $message = $signupRequest->wasRecentlyCreated 
            ? 'A verification link has been sent to your email address.'
            : 'A new verification link has been sent to your email address.';

        return back()->with('success', $message);
    }

    public function verifyEmail(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return redirect()->route('login')->with('error', 'Invalid verification link.');
        }

        $signupRequest = SignupRequest::where('token', $token)->first();

        if (!$signupRequest) {
            return redirect()->route('login')->with('error', 'Invalid verification link.');
        }

        if ($signupRequest->expires_at < now()) {
            return redirect()->route('login')->with('error', 'Verification link has expired. Please sign up again.');
        }

        $title = 'Complete Registration';
        return view('admin.auth.complete-registration', compact('signupRequest', 'title'));
    }

    public function completeRegistration(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $signupRequest = SignupRequest::where('token', $request->token)->first();

        if (!$signupRequest || $signupRequest->expires_at < now()) {
            return redirect()->route('login')->with('error', 'Invalid or expired verification link.');
        }

        $user = User::create([
            'name' => $signupRequest->name,
            'email' => $signupRequest->email,
            'password' => Hash::make($request->password),
        ]);
        
        $user->email_verified_at = now();
        $user->save();
        
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            try {
                $user->assignRole('Business Owner');
            } catch (\Exception $e) {
                // Role might not exist, proceed anyway
            }
        }

        $signupRequest->delete();

        auth()->login($user);

        return redirect()->route('dashboard');
    }
}
