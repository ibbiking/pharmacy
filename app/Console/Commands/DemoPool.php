<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoSandboxService;
use Illuminate\Console\Command;

/** Keeps private demo copies ready so a visitor's demo sign-in is instant (demo installations only). Every minute. */
class DemoPool extends Command
{
    protected $signature = 'demo:pool {--one : Build exactly one copy (used when the pool is empty at sign-in)}';

    protected $description = 'Build private demo copies ahead of time (DEMO_INSTANCE=true only)';

    public function handle(DemoSandboxService $sandboxes): int
    {
        if (! DemoSandboxService::enabled()) {
            $this->error('Refused: not a demo installation (DEMO_INSTANCE=true).');

            return self::FAILURE;
        }
        $built = $this->option('one') ? (bool) $sandboxes->build() : $sandboxes->fill((int) config('demo.pool_size', 3));
        $this->info("Built {$built} demo cop".($built == 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
