<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SalePricePreferenceSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            SalePricePreferenceSeeder::class,
        ]);
        // \App\Models\User::factory(10)->create();
    }
}
