<?php

namespace App\Console\Commands;

use App\Models\Platform\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Create or update a platform (super) admin of the /platform portal. */
class PlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin {email} {--name= : Name for a new admin} {--password= : Non-interactive (scripts only)} {--keep-password : Keep the password of an existing admin}';

    protected $description = 'Create or update a platform admin (super admin portal at /platform)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $admin = PlatformAdmin::query()->whereRaw('lower(email) = ?', [$email])->first();
        $keep = $admin && ($this->option('keep-password') || $this->option('password') === '');
        $password = null;
        if (! $keep) {
            $password = (string) ($this->option('password') ?? $this->secret('Password (8+ characters, letters and numbers)'));
            $check = Validator::make(['password' => $password], ['password' => ['required', Password::min(8)->letters()->numbers()]]);
            if ($check->fails()) {
                $this->error($check->errors()->first('password'));

                return self::FAILURE;
            }
        }
        $admin ??= new PlatformAdmin(['email' => $email]);
        $admin->name = $this->option('name') ?: ($admin->name ?: strtok($email, '@'));
        $admin->is_active = true;
        if ($password !== null) {
            $admin->password = $password;
        }
        $admin->save();
        $this->info(($admin->wasRecentlyCreated ? 'Created' : 'Updated')." platform admin {$email}. Sign in at ".url('/platform/login'));

        return self::SUCCESS;
    }
}
