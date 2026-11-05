<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A named bundle of modules with its own price, limits and trial. */
class Plan extends Model
{
    use SoftDeletes;
    use \App\Traits\Auditable;

    protected $guarded = [];
    protected $casts = [
        'modules' => 'array',
        'limits' => 'array',
        'monthly_price' => 'float',
        'yearly_price' => 'float',
        'is_visible' => 'boolean',
    ];

    public const LIMITS = ['users', 'businesses', 'products', 'invoices_per_month', 'warehouses'];

    protected static function booted(): void
    {
        // Stripe prices can't change: a new amount or currency needs a new Stripe price (created on next checkout).
        static::saving(function (self $m) {
            if ($m->isDirty(['monthly_price', 'currency'])) {
                $m->stripe_price_monthly_id = null;
            }
            if ($m->isDirty(['yearly_price', 'currency'])) {
                $m->stripe_price_yearly_id = null;
            }
        });
    }
}
