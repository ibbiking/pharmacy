<?php

namespace App\Events;

use App\Models\Documents\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** A payment was recorded ('recorded') or deleted ('voided'). */
class PaymentChanged
{
    use Dispatchable;

    public function __construct(public Payment $payment, public string $change)
    {
    }
}
