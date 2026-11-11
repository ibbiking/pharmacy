<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/** A POS sale was saved (CheckoutService, inside its transaction). */
class SaleCompleted
{
    use Dispatchable;

    public function __construct(public Invoice $invoice)
    {
    }
}
