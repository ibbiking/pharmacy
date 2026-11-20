<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoSandboxService;
use Illuminate\Console\Command;

/**
 * Hourly on a demo installation: deletes every private demo copy idle for an hour (DEMO_IDLE_MINUTES) or used longer
 * than DEMO_MAX_MINUTES, with everything the visitor added. The seeded original demo is never touched.
 */
class DemoCleanup extends Command
{
    protected $signature = 'demo:cleanup';

    protected $description = 'Delete idle private demo copies and their data (DEMO_INSTANCE=true only)';

    public function handle(DemoSandboxService $sandboxes): int
    {
        if (! DemoSandboxService::enabled()) {
            $this->error('Refused: not a demo installation (DEMO_INSTANCE=true).');

            return self::FAILURE;
        }
        $this->info('Deleted '.$sandboxes->cleanup().' idle demo cop(ies).');

        return self::SUCCESS;
    }
}
