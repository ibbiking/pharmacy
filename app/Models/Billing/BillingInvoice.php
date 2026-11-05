<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Copy of a Stripe invoice ("bill of payment") for the business's Billing page. */
class BillingInvoice extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount_due' => 'float',
        'amount_paid' => 'float',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
