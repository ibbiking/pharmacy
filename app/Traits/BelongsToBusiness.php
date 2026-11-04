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
            // Only stamp when the caller didn't set one explicitly (e.g. per-business seeders,
            // super-admin tooling). Previously an explicit business_id was silently overwritten.
            if (empty($model->business_id) && session()->has('business_id')) {
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
