<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictSalesPersonAccess
{
    /**
     * Allow sales-person users to access POS and profile routes only.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user()->hasRole('sales-person')) {
            return $next($request);
        }

        $allowedByName = $request->routeIs(
            'profile',
            'profile.update',
            'update-password',
            'logout',
            'mark-as-read',
            'read',
            'pos.*',
            'products.search',
            'products.pos.checkStock',
            'products.category-price'
        );

        $allowedByPath = $request->is(
            'admin/pos/product-discount-info/*',
            'admin/products/search',
            'admin/products/pos/check-stock',
            'admin/products/category-price'
        );

        if ($allowedByName || $allowedByPath) {
            return $next($request);
        }

        return redirect()->route('pos.index')->with(notify('You only have access to POS and profile settings.', 'warning'));
    }
}
