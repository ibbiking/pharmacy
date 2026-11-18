<?php

namespace App\Console\Commands;

use App\Services\Accounting\BooksSyncService;
use Illuminate\Console\Command;

/** Safety sweep for the books sync (phase 6): posts journals that waited for a conversion run that never finished. */
class AccountingDrainPending extends Command
{
    protected $signature = 'accounting:drain-pending';

    protected $description = 'Post waiting journals of businesses whose books conversion is not running (and fail runs stuck for 2 hours).';

    public function handle(BooksSyncService $sync): int
    {
        $this->info('Posted '.$sync->sweep().' waiting journal(s).');

        return self::SUCCESS;
    }
}
