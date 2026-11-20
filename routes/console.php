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

// Billing (Roadmap 4e): after launch pricing ends, modules need a subscription. No-op while free.
Schedule::command('billing:enforce-entitlements')
    ->dailyAt('02:30')
    ->withoutOverlapping();

// Roadmap 13d: per-business export files live 7 days; platform backups daily (super admin downloads them in Backups).
// Books sync safety sweep (phase 6): journals waiting for a conversion run that died are posted in order.
Schedule::command('accounting:drain-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('exports:prune')->dailyAt('01:15')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('analytics:weekly-summaries')->weeklyOn(1, '07:00')->withoutOverlapping(); // Roadmap 12

// Demo installations only (DEMO_INSTANCE=true, own database): fresh demo data every night (docs/DEPLOYMENT.md §12).
if (config('demo.instance')) {
    Schedule::command('demo:rebuild --force')->dailyAt(config('demo.rebuild_at', '03:00'))->withoutOverlapping();
    // Private demo copy per visitor: keep copies ready, and delete idle ones (with what the visitor added) every hour.
    Schedule::command('demo:pool')->everyMinute()->withoutOverlapping(30)->runInBackground();
    Schedule::command('demo:cleanup')->hourly()->withoutOverlapping();
}
