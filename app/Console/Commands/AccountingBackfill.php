<?php

namespace App\Console\Commands;

use App\Services\Accounting\LedgerPoster;
use Illuminate\Console\Command;

class AccountingBackfill extends Command
{
    protected $signature = 'accounting:backfill {business : Business id}';

    protected $description = 'Post (again) the whole history of a business to its books. Safe to run any time.';

    public function handle(LedgerPoster $poster): int
    {
        $counts = $poster->backfill((int) $this->argument('business'));
        if (! $counts) {
            $this->warn('Accounting is off for this business, or there was nothing to post.');

            return self::SUCCESS;
        }
        foreach ($counts as $what => $n) {
            $this->line("{$what}: {$n}");
        }

        return self::SUCCESS;
    }
}
