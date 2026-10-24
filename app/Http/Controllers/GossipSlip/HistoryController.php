<?php

namespace App\Http\Controllers\GossipSlip;

use App\Http\Controllers\Controller;
use App\Models\GossipSlip\GsAdmin;
use App\Models\GossipSlip\GsGame;

class HistoryController extends Controller
{
    protected function currentAdmin(): GsAdmin
    {
        return GsAdmin::findOrFail(session('gs_admin_id'));
    }

    public function index()
    {
        $admin = $this->currentAdmin();
        $games = GsGame::where('admin_id', $admin->id)
            ->withCount('players')
            ->withCount('rounds')
            ->latest()
            ->get();

        return view('gossip-slip.admin.history', [
            'title' => 'Game History — Gossip Slip',
            'admin' => $admin,
            'games' => $games,
        ]);
    }

    public function show(string $code)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)
            ->where('admin_id', $admin->id)
            ->with([
                'players',
                'rounds.chits.originPlayer',
                'rounds.chits.step1Player',
                'rounds.chits.step2Player',
                'rounds.chits.step3Player',
                'rounds.chits.round',
            ])
            ->firstOrFail();

        return view('gossip-slip.admin.history-show', [
            'title' => "History: Room {$code} — Gossip Slip",
            'game'  => $game,
        ]);
    }
}
