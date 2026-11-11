<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/** Stock came in through PurchaseService::create (a purchase, or a PO receipt when $source = 'po_receipt'). */
class GoodsReceived
{
    use Dispatchable;

    /** @param Collection<int, \App\Models\Purchase> $purchases */
    public function __construct(public Collection $purchases, public string $source = 'purchase')
    {
    }
}
