<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModulePrice extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    use \App\Traits\Auditable;

    protected $guarded = [];
    protected $casts = ['monthly_price' => 'float', 'yearly_price' => 'float'];

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
