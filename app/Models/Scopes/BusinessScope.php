<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BusinessScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if (session()->has('business_id') || session()->has('impersonate_business_id')) {
            if (auth()->check() && auth()->user()->hasRole('super-admin') && !session()->has('impersonate_business_id')) {
                return; // Do not apply scope for super admin natively
            }
            
            $businessId = session('impersonate_business_id') ?? session('business_id');
            if ($businessId) {
                $builder->where($model->getTable() . '.business_id', $businessId);
            }
        }
    }
}
