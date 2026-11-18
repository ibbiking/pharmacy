<?php

namespace App\Listeners;

use App\Events;
use App\Services\Accounting\LedgerPoster;

/**
 * Accounting listener (Roadmap step 10): turns business events into journals. Runs inside the source's transaction.
 * A posting problem never blocks a sale, purchase or payment: it is reported and the backfill
 * (`php artisan accounting:backfill {business}`) posts it again later.
 */
class PostToLedger
{
    public function __construct(private readonly LedgerPoster $poster)
    {
    }

    public function handle(object $event): void
    {
        try {
            // Books are being converted in the background: this posting waits its turn (posted in order afterwards).
            if (app(\App\Services\Accounting\BooksSyncService::class)->deferIfBusy($event)) {
                return;
            }
            match (true) {
                $event instanceof Events\SaleCompleted => $this->poster->posSale($event->invoice),
                $event instanceof Events\SaleReturned => $this->poster->posReturn($event->invoice, $event->returnNo),
                $event instanceof Events\GoodsReceived => $event->purchases->each(fn ($p) => $this->poster->purchase($p, $event->source)),
                $event instanceof Events\PurchaseChanged => $this->poster->purchase($event->purchase),
                $event instanceof Events\StockAdjusted => $this->poster->adjustment($event->adjustment),
                $event instanceof Events\DocumentChanged => $this->poster->document($event->document),
                $event instanceof Events\PaymentChanged => $this->poster->payment($event->payment),
                $event instanceof Events\ExpenseChanged => $this->poster->expense($event->expense),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
