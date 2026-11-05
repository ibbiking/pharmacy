<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/** Sign in (Roadmap 5.0: Inertia, throttled, session regenerated). */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function index(Request $request)
    {
        return Inertia::render('Auth/Login', [
            'email' => (string) $request->query('email', ''),
            'status' => session('status'),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ]);

        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Too many sign-in attempts. Please try again in {$seconds} seconds."]);
        }

        // Super-admin login from .env (to be replaced by a normal account in Roadmap step 13).
        $saEmail = (string) env('SUPERADMINEMAIL', '');
        $saPassword = (string) env('SUPERADMINPASSWORD', '');
        if ($saEmail !== '' && $saPassword !== '' && hash_equals(Str::lower($saEmail), Str::lower($data['email'])) && hash_equals($saPassword, $data['password'])) {
            $superAdmin = User::firstOrCreate(
                ['email' => $saEmail],
                ['name' => env('SUPERADMINNAME', 'System Super Admin'), 'password' => Hash::make($data['password'])]
            );
            if (! $superAdmin->hasRole('super-admin')) {
                $superAdmin->assignRole(Role::findOrCreate('super-admin'));
            }
            RateLimiter::clear($key);
            Auth::login($superAdmin, (bool) ($data['remember'] ?? false));

            return $this->signedIn($request);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], (bool) ($data['remember'] ?? false))) {
            RateLimiter::hit($key, 60);
            $left = self::MAX_ATTEMPTS - RateLimiter::attempts($key);
            throw ValidationException::withMessages(['email' => 'This email and password don’t match an account.'.($left > 0 && $left <= 2 ? " {$left} attempt(s) left before a short pause." : '')]);
        }

        RateLimiter::clear($key);

        return $this->signedIn($request);
    }

    private function signedIn(Request $request)
    {
        $request->session()->regenerate();
        $request->session()->forget(['impersonate_business_id', 'business_id']);

        return redirect()->intended(route('dashboard'));
    }
}
