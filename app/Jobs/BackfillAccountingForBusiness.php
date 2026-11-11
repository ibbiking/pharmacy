<?php

namespace App\Jobs;

use App\Services\Accounting\LedgerPoster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Posts a business's past sales, purchases, documents, payments and expenses when Accounting is switched on (idempotent). */
class BackfillAccountingForBusiness implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;

    public function __construct(public int $businessId)
    {
    }

    public function handle(LedgerPoster $poster): void
    {
        // Models are read with explicit business ids; no session scope applies in a worker.
        $poster->backfill($this->businessId);
    }
}
