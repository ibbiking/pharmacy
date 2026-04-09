<?php

namespace App\Traits;

use App\Models\Scopes\BusinessScope;
use App\Models\Business;

trait BelongsToBusiness
{
    /**
     * Boot the trait to add the global scope and event listeners.
     *
     * @return void
     */
    protected static function bootBelongsToBusiness()
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function ($model) {
            if (session()->has('business_id')) {
                $model->business_id = session('business_id');
            }
        });
    }

    /**
     * Relationship to the Business model.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
