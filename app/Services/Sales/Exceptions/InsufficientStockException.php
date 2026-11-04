<?php

namespace App\Services\Sales\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly float $requestedBaseQty,
        public readonly float $availableBaseQty,
    ) {
        parent::__construct("Not enough stock for product {$productId}: requested {$requestedBaseQty}, available {$availableBaseQty} (base units).");
    }
}
