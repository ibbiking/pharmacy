<?php

namespace App\Services\Sales;

use App\Models\Invoice;

final class CheckoutResult
{
    /**
     * @param  array<int, array{item_id: int, name: string, server: float, charged: float}>  $priceOverrides
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly array $priceOverrides = [],
    ) {
    }
}
