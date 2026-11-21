<?php

namespace App\Http\Middleware\Platform;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props of the /platform portal only (the business app's HandleInertiaRequests never runs here). */
class HandlePlatformInertiaRequests extends Middleware
{
    protected $rootView = 'platform';

    /** Start clean: never carry the business app's shared props (user, business, notifications…) into the portal. */
    public function handle(Request $request, \Closure $next)
    {
        \Inertia\Inertia::flushShared();

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $admin = auth('platform')->user();

        return array_merge(parent::share($request), [
            'brand' => config('app.brand', 'Suite'),
            'admin' => $admin ? ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'message' => fn () => $request->session()->get('message'),
            ],
        ]);
    }
}
