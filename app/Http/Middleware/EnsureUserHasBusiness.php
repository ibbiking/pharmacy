<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = \Illuminate\Support\Facades\Auth::user();

            if ($request->routeIs('business.setup', 'business.setup.store', 'logout')) {
                return $next($request);
            }
            
            if ($user->hasRole('super-admin')) {
                if (session()->has('impersonate_business_id')) {
                    session(['business_id' => session('impersonate_business_id')]);
                }
                return $next($request);
            }
            
            $businesses = $user->businesses;

            if ($businesses->count() === 0) {
                return redirect()->route('business.setup');
            }

            if (!session()->has('business_id') || !$businesses->contains('id', session('business_id'))) {
                session(['business_id' => $businesses->first()->id]);
            }
        }
        
        return $next($request);
    }
}
