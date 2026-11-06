<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Roadmap 13c: products of ONE business fell to their alert level in one sale. */
class StockReachedAlertLevel
{
    use Dispatchable;

    /** @param list<array{product_id:int, name:string, stock:float, alert_qty:float, unit:?string}> $alerts */
    public function __construct(public int $businessId, public ?string $invoiceNo, public array $alerts)
    {
    }
}
