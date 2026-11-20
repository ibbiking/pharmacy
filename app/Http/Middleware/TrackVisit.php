<?php

namespace App\Http\Middleware;

use App\Support\Visitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts page views per browser per day (site_visits). Only full page views and Inertia page visits (GET), never
 * assets, JSON lookups or the super admin's own screens. A failure here never breaks the page.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $request->isMethod('GET') || $request->ajax() && ! $request->header('X-Inertia') || $response->getStatusCode() >= 400) {
            return $response;
        }
        if ($request->user()?->hasRole('super-admin') || $request->is('superadmin/*', 'admin/superadmin/*', 'up', 'build/*')) {
            return $response;
        }
        try {
            if (! Schema::hasTable('site_visits')) {
                return $response;
            }
            $now = now();
            $visitor = Visitor::id($request);
            $agent = Visitor::agent($request->userAgent());
            $user = $request->user();
            $row = ['visitor_id' => $visitor, 'day' => $now->toDateString()];
            $exists = DB::table('site_visits')->where($row)->exists();
            if ($exists) {
                DB::table('site_visits')->where($row)->update(['hits' => DB::raw('hits + 1'), 'last_seen_at' => $now]
                    + ($user ? ['user_id' => $user->id, 'is_demo' => $user->isDemoAccount()] : []));
            } else {
                DB::table('site_visits')->insertOrIgnore($row + [
                    'user_id' => $user?->id, 'is_demo' => (bool) $user?->isDemoAccount(), 'ip' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'first_path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                    'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
                    'first_seen_at' => $now, 'last_seen_at' => $now,
                ] + $agent);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }
}
