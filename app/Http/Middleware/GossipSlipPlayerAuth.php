<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\GossipSlip\GsPlayer;

class GossipSlipPlayerAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = session('gs_player_token');

        if (!$token) {
            return redirect('/gossip-slip')
                ->with('error', 'Pehle apna naam select karo! 👋');
        }

        $player = GsPlayer::where('session_token', $token)->first();

        if (!$player) {
            session()->forget('gs_player_token');
            return redirect('/gossip-slip')
                ->with('error', 'Token purana ho gaya, dobara join karo! 🔄');
        }

        // Refresh heartbeat
        $player->update(['last_seen_at' => now()]);

        // Inject player into request for controllers
        $request->attributes->set('gs_player', $player);

        return $next($request);
    }
}
