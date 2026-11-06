<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * "My profile" (Roadmap 5.11a). Always the SIGNED-IN user (U-1: the old route edited whichever user was named in the
 * URL). Changing the email needs the current password; emails of other or archived accounts are refused politely.
 */
class AccountController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();

        return Inertia::render('Account/Profile', [
            'account' => ['name' => $u->name, 'email' => $u->email, 'avatar' => $u->avatar ? asset('storage/users/'.$u->avatar) : null,
                'since' => $u->created_at?->format('d M Y'), 'verified' => (bool) $u->email_verified_at,
                // A sales person's email is the one the owner invited; only the owner changes it.
                'email_locked' => $u->isSalesPersonIn((int) (session('impersonate_business_id') ?? session('business_id')))],
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:200'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($user->id)], // archived accounts keep their email too
            'current_password' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['boolean'],
        ], ['email.unique' => 'This email belongs to another account (or an archived one), so it can’t be used.']);

        $emailChanged = mb_strtolower(trim($data['email'])) !== mb_strtolower($user->email);
        if ($emailChanged && $user->isSalesPersonIn((int) (session('impersonate_business_id') ?? session('business_id')))) {
            throw ValidationException::withMessages(['email' => 'Your email is the one the business invited you with; ask the owner to change it.']);
        }
        if ($emailChanged && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Enter your current password to change your email.']);
        }

        $user->name = trim($data['name']);
        if ($emailChanged) {
            $user->email = trim($data['email']);
        }
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $name = $user->id.'-'.bin2hex(random_bytes(6)).'.'.$file->extension();
            $file->storeAs('users', $name, 'uploads');
            $user->avatar = $name;
        } elseif (! empty($data['remove_avatar'])) {
            $user->avatar = null;
        }
        $user->save();

        return back()->with('success', $emailChanged ? 'Profile saved. Sign in with your new email from now on.' : 'Profile saved.');
    }

    public function password(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'max:200', Password::min(8)->letters()->numbers()], // same rule as sign-up
        ]);
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'That isn’t your current password.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from the current one.']);
        }
        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        $request->session()->regenerate();
        // 13e: other devices are signed out (AuthenticateSession compares this hash); this one stays signed in.
        $request->session()->put('password_hash_'.auth()->getDefaultDriver(), $user->getAuthPassword());

        return back()->with('success', 'Password changed. Other devices signed in with your account were signed out.');
    }
}
