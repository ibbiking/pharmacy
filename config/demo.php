<?php

/*
|--------------------------------------------------------------------------
| Demo accounts (docs/DEMO_ACCOUNTS.md, docs/DEPLOYMENT.md §12)
|--------------------------------------------------------------------------
| One owner with a business of every type (Pharmacy, Retail & Inventory, Wholesale & Distribution), plus an
| accountant and a cashier, filled with data that went through every flow (database/seeders/DemoDataSeeder.php).
|
|  - enabled:  may the demo seeder run here? On by default everywhere except APP_ENV=production, where it needs
|              DEMO_ACCOUNTS=true.
|  - password: the demo login password. Required on a production server (DEMO_PASSWORD); elsewhere it falls back
|              to the documented test password.
|  - instance: this whole installation is a demo copy (e.g. demo.example.com with its own database). Only then may
|              `php artisan demo:rebuild` wipe the database, and it is scheduled nightly so the demo always looks fresh.
|  - emails:   the demo logins. Their email and password can't be changed from the Profile screen, so a client
|              trying things can't lock the next client out.
*/

return [
    'enabled' => env('DEMO_ACCOUNTS') !== null ? (bool) env('DEMO_ACCOUNTS') : env('APP_ENV', 'production') !== 'production',
    'password' => env('DEMO_PASSWORD'),
    'instance' => (bool) env('DEMO_INSTANCE', false),
    'rebuild_at' => env('DEMO_REBUILD_AT', '03:00'),
    // Private copies per visitor (demo installations): how many wait ready, and when an unused one is deleted.
    'pool_size' => (int) env('DEMO_POOL_SIZE', 3),
    'idle_minutes' => (int) env('DEMO_IDLE_MINUTES', 60),
    'max_minutes' => (int) env('DEMO_MAX_MINUTES', 180),
    'emails' => ['demo.owner@example.test', 'demo.accountant@example.test', 'demo.cashier@example.test'],
];
