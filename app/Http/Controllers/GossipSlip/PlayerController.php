<?php

namespace App\Http\Controllers\GossipSlip;

use App\Http\Controllers\Controller;
use App\Models\GossipSlip\GsGame;
use App\Models\GossipSlip\GsPlayer;
use App\Services\GossipSlip\ChitEngine;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    protected ChitEngine $engine;

    public function __construct(ChitEngine $engine)
    {
        $this->engine = $engine;
    }

    // -------------------------------------------------------
    // JOIN — Show player name selection
    // -------------------------------------------------------
    public function showJoin(string $code)
    {
        $game = GsGame::where('room_code', strtoupper($code))
            ->whereIn('status', ['lobby', 'active'])
            ->with('players')
            ->firstOrFail();

        // Mark currently logged-in player (if any)
        $myToken    = session('gs_player_token');
        $myPlayerId = null;
        if ($myToken) {
            $me = GsPlayer::where('session_token', $myToken)
                ->where('game_id', $game->id)
                ->first();
            $myPlayerId = $me ? $me->id : null;
        }

        return view('gossip-slip.player.join', [
            'title'      => "Join Game — Gossip Slip",
            'game'       => $game,
            'players'    => $game->players,
            'myPlayerId' => $myPlayerId,
        ]);
    }

    // -------------------------------------------------------
    // JOIN — Claim a player slot (or switch)
    // -------------------------------------------------------
    public function join(Request $request, string $code)
    {
        $request->validate(['player_id' => 'required|integer']);

        $game = GsGame::where('room_code', strtoupper($code))
            ->whereIn('status', ['lobby', 'active'])
            ->firstOrFail();

        $target = GsPlayer::where('id', $request->player_id)
            ->where('game_id', $game->id)
            ->firstOrFail();

        $myToken = session('gs_player_token');

        // Already this player — just resume
        if ($myToken && $myToken === $target->session_token) {
            $target->update(['last_seen_at' => now()]);
            return redirect()->route('gs.player.play');
        }

        // Target slot is actively in use by someone else → request switch
        if ($target->last_seen_at && $target->last_seen_at->gt(now()->subMinutes(10))) {
            // Store switch request on target
            $requesterToken = $myToken ?? session()->getId();
            $target->update([
                'switch_request_from' => $myToken
                    ? GsPlayer::where('session_token', $myToken)->where('game_id', $game->id)->value('id')
                    : null,
                'switch_requested_at' => now(),
            ]);
            // Also store pending claim info in session for when approved
            session(['gs_switch_target_id' => $target->id, 'gs_switch_game' => $game->id]);

            return back()->with('switch_pending', "Switch request bheja gaya {$target->name} ko! Unka jawab aane do. ⏳");
        }

        // Slot is free — claim it directly
        session(['gs_player_token' => $target->session_token]);
        $target->update([
            'last_seen_at'        => now(),
            'switch_request_from' => null,
            'switch_requested_at' => null,
        ]);

        return redirect()->route('gs.player.play');
    }

    // -------------------------------------------------------
    // GAME SCREEN
    // -------------------------------------------------------
    public function play(Request $request)
    {
        $player = $request->attributes->get('gs_player');
        $game   = $player->game()->with('players')->first();

        return view('gossip-slip.player.game', [
            'title'  => 'Gossip Slip — Aapki Baari!',
            'player' => $player,
            'game'   => $game,
        ]);
    }

    // -------------------------------------------------------
    // SUBMIT STEP
    // -------------------------------------------------------
    public function submitStep(Request $request)
    {
        $request->validate([
            'chit_id' => 'required|integer',
            'step'    => 'required|in:step1,step2,step3',
            'value'   => 'required|string|min:1|max:255',
        ]);

        $player = $request->attributes->get('gs_player');
        $game   = $player->game;
        $round  = $game->currentRound();

        if (!$round || $round->status !== 'step1') {
            return response()->json(['error' => 'Round active nahi hai abhi.'], 422);
        }

        // Enforce strict per-player sequential order
        if ($request->step === 'step2') {
            $c1 = \App\Models\GossipSlip\GsChit::where('round_id', $round->id)
                ->where('step1_player_id', $player->id)->first();
            if (!$c1 || $c1->name_val === null) {
                return response()->json(['error' => 'Pehle Step 1 (Naam) complete karo! 🔒'], 422);
            }
        }
        if ($request->step === 'step3') {
            $c2 = \App\Models\GossipSlip\GsChit::where('round_id', $round->id)
                ->where('step2_player_id', $player->id)->first();
            if (!$c2 || $c2->action_val === null) {
                return response()->json(['error' => 'Pehle Step 2 complete karo! 🔒'], 422);
            }
        }

        $stepPlayerField = $request->step . '_player_id';
        $stepValueField  = [
            'step1' => 'name_val',
            'step2' => 'action_val',
            'step3' => 'location_val',
        ][$request->step];

        $chit = \App\Models\GossipSlip\GsChit::where('id', $request->chit_id)
            ->where('round_id', $round->id)
            ->firstOrFail();

        if ((int) $chit->{$stepPlayerField} !== (int) $player->id) {
            return response()->json(['error' => 'Ye parchi tumhari nahi hai! 🕵️'], 403);
        }

        if ($chit->{$stepValueField} !== null) {
            return response()->json(['error' => 'Aap pehle hi likh chuke ho! 😄'], 422);
        }

        $chit->update([$stepValueField => trim($request->value)]);

        $this->engine->autoAdvanceIfReady($round);
        $round->refresh();

        return response()->json([
            'success'      => true,
            'message'      => 'Parchi fold ho gayi! ✂️🎉',
            'round_status' => $round->status,
        ]);
    }


    // -------------------------------------------------------
    // SWITCH REQUEST — Target player responds (approve/deny)
    // -------------------------------------------------------
    public function respondSwitch(Request $request)
    {
        $request->validate(['action' => 'required|in:approve,deny']);

        $player = $request->attributes->get('gs_player');

        // No pending request
        if (!$player->switch_request_from || !$player->switch_requested_at) {
            return response()->json(['error' => 'Koi switch request nahi hai.'], 404);
        }

        // Expired (> 2 min)
        if ($player->switch_requested_at->lt(now()->subMinutes(2))) {
            $player->update(['switch_request_from' => null, 'switch_requested_at' => null]);
            return response()->json(['error' => 'Request expire ho gayi thi.'], 410);
        }

        if ($request->action === 'deny') {
            $player->update(['switch_request_from' => null, 'switch_requested_at' => null]);
            return response()->json(['success' => true, 'action' => 'denied', 'message' => 'Switch deny kar diya. 👋']);
        }

        // APPROVE — Requester takes this slot, current player slot is freed
        $requesterPlayerId = $player->switch_request_from;
        $requester         = GsPlayer::where('id', $requesterPlayerId)
            ->where('game_id', $player->game_id)
            ->first();

        // Invalidate current holder's session (generate new token so old session stops working)
        $oldToken  = $player->session_token;
        $newToken  = GsPlayer::generateToken();

        // Give current player a fresh token (they're now "unjoined" on this slot)
        // Actually we swap: the requester takes over current player's slot token
        if ($requester) {
            // Requester's old slot gets current player's token (they swap identities)
            $requester->update([
                'last_seen_at'        => null,
                'switch_request_from' => null,
                'switch_requested_at' => null,
            ]);
        }

        // Update current player (holder) slot with fresh unlinked token
        $player->update([
            'session_token'       => GsPlayer::generateToken(),
            'last_seen_at'        => null,
            'switch_request_from' => null,
            'switch_requested_at' => null,
        ]);

        // Requester's session should now use old token — the requester's browser
        // will automatically log in when they use old slot they requested
        // Store the grant token in a temporary "approved" flag on requester player
        if ($requester) {
            $requester->update(['session_token' => $oldToken, 'last_seen_at' => now()]);
            session(['gs_player_token' => $oldToken]);
        }

        return response()->json([
            'success' => true,
            'action'  => 'approved',
            'message' => 'Switch ho gaya! Welcome to the other side 😄',
        ]);
    }
}
