<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PosCartReservation;

class CleanupStaleCartReservations extends Command
{
    protected $signature = 'pos:cleanup-stale-reservations';
    protected $description = 'Delete POS cart stock reservations that have gone stale (abandoned carts)';

    public function handle()
    {
        $deleted = PosCartReservation::withoutGlobalScopes()
            ->where('updated_at', '<', now()->subMinutes(PosCartReservation::STALE_MINUTES))
            ->delete();

        $this->info("Deleted {$deleted} stale reservation(s).");
        return Command::SUCCESS;
    }
}
