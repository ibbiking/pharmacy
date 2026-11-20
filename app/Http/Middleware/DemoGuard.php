<?php

namespace App\Http\Middleware;

use App\Services\Demo\DemoSandboxService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo logins (shared demo accounts and private demo copies) may try every screen and add, edit or delete business
 * data, but never touch people, roles, modules, billing, the business's own settings, exports or their login
 * (owner 2026-11-20). Also keeps a private copy marked as in use.
 */
class DemoGuard
{
    /** Route names a demo login can't change (any method but GET / HEAD). */
    private const LOCKED = [
        'settings.team*', 'settings.roles*', 'roles.*', 'users.*', 'permissions.*', 'modules.*', 'billing.*',
        'business.store', 'business.settings*', 'settings.store', 'settings.email*', 'settings.numbering*', 'settings.export*',
        'backup.*', 'profile.update', 'update-password', 'superadmin.*',
    ];

    /** Pages a demo login can't even open (creating another business would leave the demo copy). */
    private const HIDDEN = ['business.create', 'business.create.*', 'billing.*', 'settings.export*', 'backup.*', 'users.create'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isDemoAccount()) {
            return $next($request);
        }
        DemoSandboxService::touch($user);

        $name = (string) $request->route()?->getName();
        $reading = $request->isMethod('GET') || $request->isMethod('HEAD');
        if (($reading && Str::is(self::HIDDEN, $name)) || (! $reading && Str::is(self::LOCKED, $name))) {
            $message = 'This is a demo: people, roles, modules, billing, business settings and the demo login can’t be changed. Everything else is yours to try.';
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => $message], 403);
            }

            return ($reading ? redirect()->route('dashboard') : back())->with('error', $message);
        }

        return $next($request);
    }
}
