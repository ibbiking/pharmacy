<?php

namespace App\Http\Middleware;

use App\Services\Modules\ModuleManager;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Blocks routes of modules the current business has not switched on (config/modules.php 'routes').
 * Super-admins outside impersonation keep their cross-business view.
 */
class EnsureModuleActive
{
    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $module = $this->modules->moduleForRoute($request->route()?->getName());
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $user = $request->user();

        if (! $module || ! $businessId || ! $user) {
            return $next($request);
        }
        if ($user->hasRole('super-admin') && ! session()->has('impersonate_business_id')) {
            return $next($request);
        }
        if ($this->modules->isActive($businessId, $module)) {
            return $next($request);
        }

        $definition = $this->modules->definition($module);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'error' => 'module_inactive',
                'module' => $module,
                'message' => "{$definition['name']} is not active for this business.",
            ], 403);
        }

        return Inertia::render('Modules/Locked', [
            'module' => ['key' => $module] + array_intersect_key($definition, array_flip(['name', 'description', 'icon', 'accent'])),
        ])->toResponse($request)->setStatusCode(403);
    }
}
