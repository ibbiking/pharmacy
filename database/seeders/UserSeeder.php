<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Roadmap 13b: no super admin with a default password any more (this used to create admin@mail.com / "password").
        // Create one with: php artisan app:super-admin you@example.com
        if (! User::role('super-admin')->exists()) {
            $this->command?->warn('No super admin yet. Create one with: php artisan app:super-admin you@example.com');
        }
    }
}
