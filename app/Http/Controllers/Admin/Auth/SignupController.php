<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PlatformMessage;
use App\Models\SignupRequest;
use App\Models\User;
use App\Support\BusinessTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Sign-up (Roadmap 5.0): business type (picture blocks) + name + email → verification email →
 * set password → signed in → create-business wizard with the type preselected → modules → done.
 */
class SignupController extends Controller
{
    public const LINK_HOURS = 24;
    public const RESEND_SECONDS = 60;

    public function showSignupForm(Request $request)
    {
        $type = $request->query('type');

        return Inertia::render('Auth/Signup', [
            'types' => BusinessTypes::forScreens(),
            'preselectType' => BusinessTypes::exists($type) ? $type : null,
            'free' => \App\Models\PlatformSetting::freeForEveryone(),
        ]);
    }

    public function processSignup(Request $request)
    {
        $data = $request->validate([
            'business_type' => ['required', Rule::in(BusinessTypes::keys())],
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ], [
            'business_type.required' => 'Choose what kind of business you run.',
        ]);
        $data['email'] = Str::lower(trim($data['email']));

        if ($existing = User::withTrashed()->where('email', $data['email'])->first()) {
            throw ValidationException::withMessages(['email' => $existing->trashed()
                ? 'This email belongs to an archived account. Please contact support to restore it.'
                : 'An account with this email already exists. Sign in, or use “Forgot password”.']);
        }

        $signup = SignupRequest::updateOrCreate(['email' => $data['email']], [
            'name' => trim($data['name']),
            'business_type' => $data['business_type'],
            'token' => Str::random(64),
            'expires_at' => now()->addHours(self::LINK_HOURS),
        ]);

        if (! $this->sendLink($signup)) {
            return back()->with('error', 'We couldn’t send the email right now. Please check the address and try again in a minute.');
        }

        return redirect()->route('signup.sent', ['email' => $signup->email]);
    }

    public function sent(Request $request)
    {
        $email = Str::lower((string) $request->query('email', ''));
        $signup = $email ? SignupRequest::where('email', $email)->first() : null;
        if (! $signup) {
            return redirect()->route('signup');
        }

        return Inertia::render('Auth/CheckEmail', [
            'email' => $signup->email,
            'resendIn' => $this->resendWait($signup),
            'hours' => self::LINK_HOURS,
            'devHint' => app()->environment('local') && in_array(config('mail.default'), ['log', 'array'], true),
        ]);
    }

    public function resend(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $signup = SignupRequest::where('email', Str::lower($data['email']))->first();
        if (! $signup) {
            return redirect()->route('signup')->with('error', 'Please sign up again: we couldn’t find that request.');
        }
        if ($wait = $this->resendWait($signup)) {
            return back()->with('error', "Please wait {$wait} seconds before sending another email.");
        }

        $signup->update(['token' => Str::random(64), 'expires_at' => now()->addHours(self::LINK_HOURS)]);

        return $this->sendLink($signup)
            ? back()->with('success', 'A new link is on its way. The previous link no longer works.')
            : back()->with('error', 'We couldn’t send the email right now. Please try again in a minute.');
    }

    public function verifyEmail(Request $request)
    {
        $signup = $this->validSignup((string) $request->query('token', ''), $problem);
        if (! $signup) {
            return Inertia::render('Auth/LinkProblem', ['problem' => $problem]);
        }

        return Inertia::render('Auth/SetPassword', [
            'token' => $signup->token,
            'name' => $signup->name,
            'email' => $signup->email,
            'type' => BusinessTypes::TYPES[$signup->business_type]['name'] ?? null,
        ]);
    }

    public function completeRegistration(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'max:200', Password::min(8)->letters()->numbers()],
        ]);

        $signup = $this->validSignup($data['token'], $problem);
        if (! $signup) {
            return Inertia::render('Auth/LinkProblem', ['problem' => $problem]);
        }

        try {
            $user = DB::transaction(function () use ($signup, $data) {
                if (User::withTrashed()->where('email', $signup->email)->lockForUpdate()->exists()) {
                    return null;
                }
                $user = User::create(['name' => $signup->name, 'email' => $signup->email, 'password' => Hash::make($data['password'])]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole(Role::findOrCreate('Business Owner'));
                $signup->delete();

                return $user;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Something went wrong while creating your account. Please try again.');
        }

        if (! $user) {
            return redirect()->route('login', ['email' => $signup->email])->with('status', 'You already have an account. Please sign in.');
        }

        auth()->login($user);
        $request->session()->regenerate();
        session(['signup_business_type' => $signup->business_type]);

        return redirect()->route('business.create', ['type' => $signup->business_type])
            ->with('success', 'Your account is ready. One last step: tell us about your business.');
    }

    private function validSignup(string $token, ?string &$problem): ?SignupRequest
    {
        $signup = $token !== '' ? SignupRequest::where('token', $token)->first() : null;
        $problem = match (true) {
            $signup === null => 'invalid',
            $signup->expires_at !== null && $signup->expires_at->isPast() => 'expired',
            default => null,
        };

        return $problem ? null : $signup;
    }

    private function resendWait(SignupRequest $signup): int
    {
        $elapsed = (int) $signup->updated_at->diffInSeconds(now(), true);

        return max(0, self::RESEND_SECONDS - $elapsed);
    }

    private function sendLink(SignupRequest $signup): bool
    {
        $type = BusinessTypes::TYPES[$signup->business_type]['name'] ?? null;
        try {
            Mail::to($signup->email)->send(new PlatformMessage(
                mailSubject: 'Confirm your email to finish signing up',
                title: "Welcome, {$signup->name}!",
                lines: [
                    'Confirm your email address to create your '.config('app.brand', 'PharmaSuite').' account'.($type ? " for your {$type} business" : '').'.',
                    'The button below works once and expires in '.self::LINK_HOURS.' hours.',
                ],
                action: ['Confirm email & set password', route('verification.verify', ['token' => $signup->token])],
                outro: 'If you didn’t sign up, you can ignore this email: no account is created without it.',
                preheader: 'One click to confirm your email.',
            ));

            return true;
        } catch (Throwable $e) {
            Log::warning('Sign-up email could not be sent', ['email' => $signup->email, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
