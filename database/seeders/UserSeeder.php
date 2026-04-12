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
        $user = User::firstOrCreate(
            ['email' => env('SUPERADMINEMAIL', 'admin@mail.com')],
            [
                'name' => env('SUPERADMINNAME', 'Super Admin'),
                'password' => Hash::make(env('SUPERADMINPASSWORD', 'password')),
            ]
        );
        
        $user->assignRole('super-admin');
    }
}
