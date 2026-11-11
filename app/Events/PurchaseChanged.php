<?php

namespace App\Events;

use App\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;

/** A purchase was edited ($change = 'updated') or archived ('archived'): its postings are redone or reversed. */
class PurchaseChanged
{
    use Dispatchable;

    public function __construct(public Purchase $purchase, public string $change)
    {
    }
}
