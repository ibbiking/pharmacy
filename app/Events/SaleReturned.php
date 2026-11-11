<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/** Items of a POS invoice were returned under $returnNo (ReturnService, inside its transaction). */
class SaleReturned
{
    use Dispatchable;

    public function __construct(public Invoice $invoice, public string $returnNo)
    {
    }
}
