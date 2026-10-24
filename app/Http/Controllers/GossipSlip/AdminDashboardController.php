<?php

namespace App\Http\Controllers\GossipSlip;

use App\Http\Controllers\Controller;
use App\Models\GossipSlip\GsAdmin;
use App\Models\GossipSlip\GsChit;
use App\Models\GossipSlip\GsGame;
use App\Models\GossipSlip\GsPlayer;
use App\Models\GossipSlip\GsRound;
use App\Services\GossipSlip\ChitEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminDashboardController extends Controller
{
    protected ChitEngine $engine;

    public function __construct(ChitEngine $engine)
    {
        $this->engine = $engine;
    }

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

        return view('gossip-slip.admin.dashboard', [
            'title' => 'Dashboard — Gossip Slip',
            'admin' => $admin,
            'games' => $games,
        ]);
    }

    public function create()
    {
        return view('gossip-slip.admin.create-game', [
            'title' => 'Naya Game Banao — Gossip Slip',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'player_names'      => 'required|array|min:2',
            'player_names.*'    => 'required|string|max:100',
            'admin_plays'       => 'nullable',
            'admin_player_name' => 'nullable|string|max:100',
        ]);

        $admin = $this->currentAdmin();

        // Generate unique 6-char uppercase room code
        do {
            $roomCode = strtoupper(Str::random(6));
        } while (GsGame::where('room_code', $roomCode)->exists());

        $game = GsGame::create([
            'room_code' => $roomCode,
            'admin_id'  => $admin->id,
            'status'    => 'lobby',
        ]);

        // Create regular player slots
        foreach ($request->player_names as $name) {
            $name = trim($name);
            if ($name !== '') {
                GsPlayer::create([
                    'game_id'       => $game->id,
                    'name'          => $name,
                    'session_token' => GsPlayer::generateToken(),
                    'is_admin'      => false,
                ]);
            }
        }

        // Admin also playing? Create their player slot and auto-set session
        if ($request->input('admin_plays') && $request->filled('admin_player_name')) {
            $adminPlayer = GsPlayer::create([
                'game_id'       => $game->id,
                'name'          => trim($request->admin_player_name),
                'session_token' => GsPlayer::generateToken(),
                'is_admin'      => true,
                'last_seen_at'  => now(),
            ]);
            $game->update(['admin_player_id' => $adminPlayer->id]);
            // Set session so admin can play directly from lobby
            session(['gs_player_token' => $adminPlayer->session_token]);
        }

        return redirect()->route('gs.admin.games.lobby', $roomCode)
            ->with('success', "Game ready! Room code: {$roomCode} 🎮");
    }

    public function lobby(string $code)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)
            ->where('admin_id', $admin->id)
            ->with(['players'])
            ->firstOrFail();

        $currentRound = $game->currentRound();
        $joinUrl      = url('/gossip-slip/join/' . $code);

        return view('gossip-slip.admin.lobby', [
            'title'        => "Room {$code} — Gossip Slip",
            'game'         => $game,
            'currentRound' => $currentRound,
            'joinUrl'      => $joinUrl,
        ]);
    }

    public function startRound(Request $request, string $code)
    {
        $request->validate(['gender_mode' => 'required|in:male,female']);

        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)
            ->where('admin_id', $admin->id)
            ->firstOrFail();

        // Mark any active round as finished
        $game->rounds()->whereNotIn('status', ['finished'])->update(['status' => 'finished']);

        $roundNumber = $game->nextRoundNumber();

        $round = GsRound::create([
            'game_id'      => $game->id,
            'round_number' => $roundNumber,
            'gender_mode'  => $request->gender_mode,
            'status'       => 'step1',
        ]);

        $game->update(['status' => 'active']);

        $players = $game->players()->orderBy('id')->get();
        $this->engine->createChitsForRound($round, $players);

        return response()->json([
            'success'  => true,
            'message'  => "Round {$roundNumber} shuru! Sab likhna shuru karo! 🎯",
            'round_id' => $round->id,
        ]);
    }

    public function finishRound(Request $request, string $code)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)->where('admin_id', $admin->id)->firstOrFail();
        $round = $game->currentRound();
        if ($round) {
            $round->update(['status' => 'finished']);
        }
        return response()->json(['success' => true, 'message' => 'Round khatam! 🏁']);
    }

    public function endGame(Request $request, string $code)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)->where('admin_id', $admin->id)->firstOrFail();
        $game->rounds()->whereNotIn('status', ['finished'])->update(['status' => 'finished']);
        $game->update(['status' => 'completed']);
        return response()->json(['success' => true, 'message' => 'Game khatam! Mazaa aaya? 🎊']);
    }

    public function addPlayer(Request $request, string $code)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)->where('admin_id', $admin->id)->firstOrFail();

        if ($game->status !== 'lobby') {
            return response()->json([
                'success' => false,
                'message' => 'Bhai, game shuru ho chuka hai! Lobby mein hi add ho sakta tha.',
            ], 422);
        }

        $player = GsPlayer::create([
            'game_id'       => $game->id,
            'name'          => trim($request->name),
            'session_token' => GsPlayer::generateToken(),
            'is_admin'      => false,
        ]);

        return response()->json([
            'success' => true,
            'player'  => ['id' => $player->id, 'name' => $player->name],
            'message' => "{$player->name} lobby mein aa gaya! 👋",
        ]);
    }

    public function removePlayer(Request $request, string $code, int $playerId)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)->where('admin_id', $admin->id)->firstOrFail();

        // Block removal while a round is actively in progress
        $activeRound = $game->rounds()
            ->whereIn('status', ['step1', 'step2', 'step3'])
            ->exists();

        if ($activeRound) {
            return response()->json([
                'success' => false,
                'message' => 'Round chal raha hai! Pehle round roko, phir hatao. 🚫',
            ], 422);
        }

        $player = GsPlayer::where('id', $playerId)
            ->where('game_id', $game->id)
            ->firstOrFail();

        // Cannot remove the admin's own player slot
        if ($game->admin_player_id && (int)$game->admin_player_id === $playerId) {
            return response()->json([
                'success' => false,
                'message' => 'Aap khud ko nahi hata sakte! 😅',
            ], 422);
        }

        $name = $player->name;
        $player->delete();

        return response()->json([
            'success' => true,
            'message' => "{$name} ko hata diya gaya! 👋",
        ]);
    }

    public function revealChit(Request $request, string $code, int $chitId)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)->where('admin_id', $admin->id)->firstOrFail();

        $chit = GsChit::whereHas('round', function ($q) use ($game) {
            $q->where('game_id', $game->id);
        })
        ->where('id', $chitId)
        ->with(['round', 'originPlayer', 'step1Player', 'step2Player', 'step3Player'])
        ->firstOrFail();

        $chit->update(['is_revealed' => true]);

        return response()->json([
            'success'      => true,
            'sentence'     => $chit->getRevealSentence(),
            'name_val'     => $chit->name_val,
            'action_val'   => $chit->action_val,
            'location_val' => $chit->location_val,
            'gender_mode'  => optional($chit->round)->gender_mode,
            'origin'       => optional($chit->originPlayer)->name,
            'step1_by'     => optional($chit->step1Player)->name,
            'step2_by'     => optional($chit->step2Player)->name,
            'step3_by'     => optional($chit->step3Player)->name,
        ]);
    }

    public function revealPage(string $code)
    {
        $admin = $this->currentAdmin();
        $game  = GsGame::where('room_code', $code)
            ->where('admin_id', $admin->id)
            ->with(['players'])
            ->firstOrFail();

        $round = $game->currentRound() ?? $game->latestRound();

        if (!$round) {
            return redirect()->route('gs.admin.games.lobby', $code)
                ->with('error', 'Koi round nahi mila!');
        }

        $chits = $round->chits()
            ->with(['originPlayer', 'step1Player', 'step2Player', 'step3Player'])
            ->get();

        return view('gossip-slip.admin.reveal', [
            'title' => "Reveal — Room {$code}",
            'game'  => $game,
            'round' => $round,
            'chits' => $chits,
            'code'  => $code,
        ]);
    }

    // -------------------------------------------------------
    // ADMIN PLAYER SUBMIT — admin submits their own chit step
    // Uses admin session auth (no gs_player_token needed)
    // -------------------------------------------------------
    public function adminPlayerSubmit(\Illuminate\Http\Request $request, string $code)
    {
        $request->validate([
            'chit_id' => 'required|integer',
            'step'    => 'required|in:step1,step2,step3',
            'value'   => 'required|string|min:1|max:255',
        ]);

        $admin  = $this->currentAdmin();
        $game   = GsGame::where('room_code', strtoupper($code))
                        ->where('admin_id', $admin->id)
                        ->firstOrFail();

        if (!$game->admin_player_id) {
            return response()->json(['error' => 'Admin player set nahi hai.'], 404);
        }

        $adminPlayer = GsPlayer::find($game->admin_player_id);
        if (!$adminPlayer) {
            return response()->json(['error' => 'Admin player nahi mila.'], 404);
        }

        $round = $game->currentRound();
        if (!$round || $round->status !== 'step1') {
            return response()->json(['error' => 'Round active nahi hai abhi.'], 422);
        }

        $stepPlayerField = $request->step . '_player_id';
        $stepValueField  = [
            'step1' => 'name_val',
            'step2' => 'action_val',
            'step3' => 'location_val',
        ][$request->step];

        $chit = GsChit::where('id', $request->chit_id)
                      ->where('round_id', $round->id)
                      ->first();

        if (!$chit) {
            return response()->json(['error' => 'Chit nahi mili.'], 404);
        }

        if ((int) $chit->{$stepPlayerField} !== (int) $adminPlayer->id) {
            return response()->json(['error' => 'Ye parchi admin ki nahi hai.'], 403);
        }

        if ($chit->{$stepValueField} !== null) {
            return response()->json(['error' => 'Pehle hi likh chuke ho!'], 422);
        }

        $chit->update([$stepValueField => trim($request->value)]);
        $this->engine->autoAdvanceIfReady($round);
        $round->refresh();

        return response()->json([
            'success'      => true,
            'message'      => 'Fold ho gaya!',
            'round_status' => $round->status,
        ]);
    }
}

