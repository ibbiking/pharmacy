<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Refreshes a demo installation (docs/DEPLOYMENT.md §12): deletes every private demo copy and the seeded demo, then
 * seeds the demo again with today's dates. Super admins and the visit / sign-in logs stay. `--fresh` empties the
 * whole database first (first install). Only on an installation marked DEMO_INSTANCE=true, with its own database.
 * Scheduled nightly there (routes/console.php).
 */
class DemoRebuild extends Command
{
    protected $signature = 'demo:rebuild {--force : Run without asking} {--fresh : Empty the whole database first (first install)}';

    protected $description = 'Wipe this demo installation’s database and seed the demo accounts again (DEMO_INSTANCE=true only)';

    public function handle(): int
    {
        if (! config('demo.instance')) {
            $this->error('Refused: this is not a demo installation (DEMO_INSTANCE=true). It would delete every business in this database.');

            return self::FAILURE;
        }
        if (! config('demo.enabled') || (app()->environment('production') && ! config('demo.password'))) {
            $this->error('Set DEMO_ACCOUNTS=true and DEMO_PASSWORD in .env first.');

            return self::FAILURE;
        }
        $db = config('database.connections.'.config('database.default').'.database');
        $fresh = (bool) $this->option('fresh');
        $what = $fresh ? "everything in the database “{$db}”" : 'all demo data (accounts and visit logs stay)';
        if (! $this->option('force') && ! $this->confirm("Delete {$what} and rebuild the demo?")) {
            return self::FAILURE;
        }

        $maintenance = ! app()->runningUnitTests();
        if ($maintenance) {
            $this->call('down', ['--retry' => 60]);
        }
        try {
            if ($fresh) {
                $this->call('migrate:fresh', ['--force' => true]);
                $this->call('db:seed', ['--force' => true]);
            } else {
                $this->call('migrate', ['--force' => true]);
                app(\App\Services\Demo\DemoSandboxService::class)->purgeAllDemoData();
            }
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);
            $this->call('permission:cache-reset');
        } finally {
            if ($maintenance) {
                $this->call('up');
            }
        }
        $this->info('Demo rebuilt.');

        return self::SUCCESS;
    }
}
