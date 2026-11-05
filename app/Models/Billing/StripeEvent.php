<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

/** Webhook inbox (append-only): one row per Stripe event id, so a resent event is processed once. */
class StripeEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
}
