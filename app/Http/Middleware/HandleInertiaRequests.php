<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\BusinessWording;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Props shared with every Inertia page (resources/js). Classic Blade pages are unaffected.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function share(Request $request): array
    {
        $user = $request->user();
        $businessId = session('impersonate_business_id') ?? session('business_id');
        $business = $businessId ? Business::find($businessId) : null;
        $type = $business?->business_type ?? BusinessWording::DEFAULT_TYPE;
        $wording = BusinessWording::for($type);

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ? asset('storage/users/'.$user->avatar) : null,
                    'roles' => $user->getRoleNames(),
                    'theme' => $user->theme ?? 'system',
                    'is_owner' => $user->isOwnerOf($business?->id),
                ] : null,
            ],
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'type' => $type,
                'currency' => $business->currencySymbol(),
                'impersonating' => session()->has('impersonate_business_id'),
            ] : null,
            // Small list for the switcher (a user rarely has many); count shows the total.
            'businesses' => $user ? fn () => [
                'items' => $user->activeBusinesses()->orderBy('name')->limit(20)->get(['businesses.id', 'businesses.name'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
                'count' => $user->activeBusinesses()->count(),
            ] : null,
            'wording' => $wording,
            'navigation' => $user ? fn () => Navigation::for($user, $wording, $request->route()?->getName(), $business?->id) : [],
            'brand' => config('app.brand', 'PharmaSuite'),
            // Bell badge (Roadmap 13c): unread notifications of the current business.
            'unreadNotifications' => fn () => $user && $business ? \App\Http\Controllers\Admin\NotificationController::queryFor($request)->whereNull('read_at')->count() : 0,
            'activeModules' => fn () => $business ? app(\App\Services\Modules\ModuleManager::class)->activeFor($business->id) : [],
            'flash' => fn () => [
                'success' => session('success'),
                'error' => session('error'),
                'message' => session('message') ?? session('info'),
            ],
        ]);
    }
}
