<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Roadmap 13b: a super admin is a normal account (the .env login shortcut is gone).
 * Creates the account, or promotes an existing user, and sets its password (same rule as sign-up).
 *
 *   php artisan app:super-admin owner@example.com
 *   php artisan app:super-admin owner@example.com --name="Platform Admin" --keep-password
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'app:super-admin {email} {--name= : Name for a new account} {--keep-password : Promote an existing user without changing their password} {--password= : Non-interactive (scripts only; avoid in shell history)}';

    protected $description = 'Create a super-admin account or promote an existing user';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email:rfc|max:191'])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->where('email', $email)->first();
        $setPassword = ! ($user && $this->option('keep-password'));
        $password = null;
        if ($setPassword) {
            $password = (string) ($this->option('password') ?? $this->secret('Password (8+ characters, letters and numbers)'));
            if ($this->option('password') === null && $password !== (string) $this->secret('Repeat the password')) {
                $this->error('The passwords do not match.');

                return self::FAILURE;
            }
            $check = Validator::make(['password' => $password], ['password' => ['required', Password::min(8)->letters()->numbers()]]);
            if ($check->fails()) {
                $this->error($check->errors()->first('password'));

                return self::FAILURE;
            }
        }

        if (! $user) {
            $user = new User(['email' => $email, 'name' => $this->option('name') ?: 'Platform Admin']);
            $user->email_verified_at = now();
        } elseif ($user->trashed()) {
            $user->restore();
        }
        if ($password !== null) {
            $user->password = Hash::make($password);
        }
        $user->save();
        $user->assignRole(Role::findOrCreate('super-admin'));

        $this->info("{$email} is a super admin".($password !== null ? ' with the new password.' : '.'));

        return self::SUCCESS;
    }
}
