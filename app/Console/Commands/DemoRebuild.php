<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Rebuilds a demo installation from scratch: empty database, base data, demo accounts with fresh, recent data
 * (docs/DEPLOYMENT.md §12). Only on an installation marked DEMO_INSTANCE=true, which has its own database: it
 * deletes EVERYTHING in that database. Scheduled nightly there (routes/console.php).
 */
class DemoRebuild extends Command
{
    protected $signature = 'demo:rebuild {--force : Run without asking}';

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
        if (! $this->option('force') && ! $this->confirm("Delete everything in the database “{$db}” and rebuild the demo?")) {
            return self::FAILURE;
        }

        $this->call('down', ['--retry' => 60]);
        try {
            $this->call('migrate:fresh', ['--force' => true]);
            $this->call('db:seed', ['--force' => true]);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);
            $this->call('permission:cache-reset');
        } finally {
            $this->call('up');
        }
        $this->info('Demo rebuilt.');

        return self::SUCCESS;
    }
}
