<?php

namespace App\Services\Billing\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Any billing problem, carrying a message that is safe to show to the business owner.
 * The technical detail (never containing keys) goes to the log, not to the screen.
 *
 * reason: not_configured | card | rate_limit | network | invalid_keys | invalid_request | provider |
 *         webhook_signature | state | currency
 */
class BillingException extends RuntimeException
{
    public function __construct(
        public readonly string $userMessage,
        public readonly string $reason = 'provider',
        ?Throwable $previous = null,
    ) {
        parent::__construct($userMessage, 0, $previous);
    }

    /** HTTP status for JSON callers. */
    public function status(): int
    {
        return match ($this->reason) {
            'not_configured', 'network', 'rate_limit', 'provider', 'invalid_keys' => 503,
            'webhook_signature' => 400,
            default => 422,
        };
    }
}
