<?php

namespace App\Http\Middleware\Platform;

use App\Models\Scopes\BusinessScope;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every /platform page except the login: a signed-in, active platform admin (guard `platform`). The business scope is
 * switched off for these requests: platform screens read every business explicitly.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $previous = Auth::getDefaultDriver();
        Auth::shouldUse('platform');
        $admin = Auth::guard('platform')->user();
        if (! $admin) {
            Auth::shouldUse($previous);

            return redirect()->guest(route('platform.login'));
        }
        if (! $admin->canSignIn()) {
            Auth::guard('platform')->logout();
            Auth::shouldUse($previous);

            return redirect()->route('platform.login')->with('error', 'This platform admin account is switched off.');
        }
        BusinessScope::$platform = true;
        try {
            return $next($request);
        } finally {
            BusinessScope::$platform = false; // never leaks into the next request of a long-running process / tests
            Auth::shouldUse($previous);
        }
    }
}
