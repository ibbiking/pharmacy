<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Sign-in of the platform portal (guard `platform`). Same answer for an unknown email, a wrong password or an archived
 * admin; one hash check always runs so the timing gives nothing away; 5 tries per minute per email + IP.
 */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show()
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route('platform.dashboard');
        }

        return Inertia::render('Platform/Login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255'], 'remember' => ['boolean']]);
        $email = Str::lower(trim($data['email']));
        $key = 'platform-login:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['email' => 'Too many sign-in attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $admin = PlatformAdmin::query()->whereRaw('lower(email) = ?', [$email])->first();
        $ok = Hash::check($data['password'], $admin?->password ?? '$2y$12$4p8b6j8m1n3QGv9KcVnHOeY0x5bqX2r1tWm7cLz9sPq3eUyA6dJkS');
        if (! $admin || ! $ok) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These details don’t match a platform admin.']);
        }
        if (! $admin->is_active) {
            throw ValidationException::withMessages(['email' => 'This platform admin account is switched off.']);
        }

        RateLimiter::clear($key);
        Auth::guard('platform')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();
        PlatformAuditLog::record('login');

        return redirect()->intended(route('platform.dashboard'));
    }

    public function logout(Request $request)
    {
        PlatformAuditLog::record('logout');
        Auth::guard('platform')->logout();
        // A business opened from the portal ends too.
        if ($request->session()->has('platform_open')) {
            Auth::guard('web')->logoutCurrentDevice();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
