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
                    'is_owner' => $user->hasRole(['Business Owner', 'super-admin']),
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
                'items' => $user->businesses()->orderBy('name')->limit(20)->get(['businesses.id', 'businesses.name'])
                    ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
                'count' => $user->businesses()->count(),
            ] : null,
            'wording' => $wording,
            'navigation' => fn () => Navigation::for($user, $wording, $request->route()?->getName()),
            'flash' => fn () => [
                'success' => session('success'),
                'error' => session('error'),
                'message' => session('message'),
            ],
        ]);
    }
}
