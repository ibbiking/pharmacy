<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GossipSlipAdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('gs_admin_id')) {
            return redirect()->route('gs.admin.login')
                ->with('error', 'Pehle login karo, phir andar aao! 🔐');
        }
        return $next($request);
    }
}
