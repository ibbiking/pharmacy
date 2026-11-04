<?php

namespace App\Services\Sales\Exceptions;

use RuntimeException;

/**
 * A cart line breaks a POS rule (price change not allowed, price below the floor, discount above
 * the cap). Carries a machine-readable code and context for the POS to show / correct.
 */
class SaleRuleViolation extends RuntimeException
{
    public const PRICE_CHANGE_NOT_ALLOWED = 'price_change_not_allowed';
    public const PRICE_BELOW_MINIMUM = 'price_below_minimum';
    public const LINE_DISCOUNT_EXCEEDED = 'discount_exceeded';
    public const INVOICE_DISCOUNT_EXCEEDED = 'invoice_discount_exceeded';
    public const INVALID_LINE = 'invalid_line';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
