<?php

namespace App\Http\Middleware;

use App\Services\Access\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Checks the permission a route needs (config/route_permissions.php) against the user's role in
 * the current business (Roadmap 4i-4). Owners and super-admins always pass.
 */
class EnsureRoutePermission
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $name = $request->route()?->getName();
        if (! $user || ! $name) {
            return $next($request);
        }

        $required = self::requiredFor($name);
        if ($required === null) {
            return $next($request);
        }

        if ($required === '@super-admin') {
            return $user->hasRole('super-admin') ? $next($request) : $this->deny($request, null);
        }

        return $user->can($required) ? $next($request) : $this->deny($request, $required);
    }

    /** The permission a route needs, or null when it is open to every member / unmapped. */
    public static function requiredFor(string $routeName): ?string
    {
        foreach (config('route_permissions.map', []) as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                return $permission;
            }
        }

        return null;
    }

    private function deny(Request $request, ?string $permission)
    {
        $label = $permission ? ($this->permissionLabel($permission) ?? $permission) : 'Super admin only';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['error' => 'forbidden', 'permission' => $permission, 'message' => "You don't have access to this ({$label})."], 403);
        }

        return Inertia::render('Errors/Forbidden', [
            'permission' => $label,
            'canSell' => $request->user()->can('pos.sell'),
        ])->toResponse($request)->setStatusCode(403);
    }

    private function permissionLabel(string $permission): ?string
    {
        foreach ($this->permissions->catalogue() as $module) {
            if (isset($module['permissions'][$permission])) {
                return $module['permissions'][$permission][0];
            }
        }

        return null;
    }
}
