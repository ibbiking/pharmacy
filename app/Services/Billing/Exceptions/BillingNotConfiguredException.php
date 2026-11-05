<?php

namespace App\Services\Billing\Exceptions;

/** Stripe keys are not set yet (STRIPE_KEY / STRIPE_SECRET / STRIPE_WEBHOOK_SECRET in .env). */
class BillingNotConfiguredException extends BillingException
{
    public function __construct(string $what = 'Online payments')
    {
        parent::__construct("{$what} are not connected yet. The platform owner needs to add the Stripe keys; nothing has been charged.", 'not_configured');
    }
}
