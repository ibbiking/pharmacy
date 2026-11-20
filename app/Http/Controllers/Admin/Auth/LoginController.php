<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Sign in (Roadmap 5.0: Inertia, throttled, session regenerated). */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function index(Request $request)
    {
        $demo = \App\Services\Demo\DemoSandboxService::enabled();

        return Inertia::render('Auth/Login', [
            'email' => (string) $request->query('email', ''),
            'status' => session('status'),
            // Demo installation: the public demo logins are filled in (owner first; accountant and cashier one click away).
            'demo' => $demo ? [
                'password' => \App\Services\Demo\DemoSandboxService::publicPassword(),
                'reset_at' => \Illuminate\Support\Carbon::createFromFormat('H:i', config('demo.rebuild_at', '00:00'))->format('g:i A'),
                'timezone' => config('demo.timezone_label', 'Pakistan time'),
                'idle_hours' => round(config('demo.idle_minutes', 120) / 60, 1),
                'logins' => collect(config('demo.emails'))->values()->map(fn ($email, $i) => ['email' => $email, 'role' => ['Owner', 'Accountant', 'Cashier'][$i] ?? 'Demo'])->all(),
            ] : null,
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

        // Demo installation: the public demo login hands this browser its own private copy of the demo businesses.
        $demoRole = \App\Services\Demo\DemoSandboxService::enabled() ? \App\Services\Demo\DemoSandboxService::roleForEmail($data['email']) : null;
        if ($demoRole && hash_equals(\App\Services\Demo\DemoSandboxService::publicPassword(), $data['password'])) {
            try {
                Auth::login(app(\App\Services\Demo\DemoSandboxService::class)->userFor($request, $demoRole));
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['email' => $e->getMessage()]);
            }
            RateLimiter::clear($key);

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
