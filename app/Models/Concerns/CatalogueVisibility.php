<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared generic-catalogue master data (manufacturers, types, strengths, formulas, units): a business sees approved
 * names plus the ones it suggested itself; a super admin (not impersonating) sees everything. Roadmap 5.6b.
 */
trait CatalogueVisibility
{
    public function scopeVisibleInCatalogue(Builder $query): Builder
    {
        $user = auth()->user();
        if ($user && $user->hasRole('super-admin') && ! session()->has('impersonate_business_id')) {
            return $query;
        }
        $businessId = session('impersonate_business_id') ?? session('business_id');

        return $query->where(fn ($q) => $q->where($this->qualifyColumn('status'), 'approved')
            ->when($businessId, fn ($x) => $x->orWhere($this->qualifyColumn('suggested_by_business_id'), $businessId)));
    }
}
