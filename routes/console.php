<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (moved here from the old App\Console\Kernel::schedule())
|--------------------------------------------------------------------------
| Requires `php artisan schedule:run` every minute in system cron.
*/
Schedule::command('generic-products:process-bulk-imports')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('pos:cleanup-stale-reservations')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
