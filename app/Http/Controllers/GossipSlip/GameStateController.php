<?php

namespace App\Http\Controllers\GossipSlip;

use App\Http\Controllers\Controller;
use App\Models\GossipSlip\GsGame;
use App\Models\GossipSlip\GsPlayer;
use App\Services\GossipSlip\ChitEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameStateController extends Controller
{
    protected ChitEngine $engine;

    public function __construct(ChitEngine $engine)
    {
        $this->engine = $engine;
    }

    // -------------------------------------------------------
    // PLAYER STATE — polled every 0.6s
    // -------------------------------------------------------
    public function playerState(Request $request): JsonResponse
    {
        $token = session('gs_player_token');

        if (!$token) {
            return response()->json(['error' => 'Not authenticated', 'redirect' => '/gossip-slip'], 401);
        }

        $player = GsPlayer::where('session_token', $token)->first();

        if (!$player) {
            return response()->json(['error' => 'Player not found', 'redirect' => '/gossip-slip'], 404);
        }

        // Heartbeat
        $player->update(['last_seen_at' => now()]);

        $game  = $player->game;
        $round = $game->currentRound();

        // Switch request check
        $switchRequest = null;
        if ($player->switch_request_from) {
            $reqAt = $player->switch_requested_at
                ? \Carbon\Carbon::parse($player->switch_requested_at)
                : null;
            if ($reqAt && $reqAt->gt(now()->subMinutes(2))) {
                $requester     = GsPlayer::find($player->switch_request_from);
                $switchRequest = [
                    'requester_name' => $requester ? $requester->name : 'Koi anjaan',
                    'requested_at'   => $reqAt->toIso8601String(),
                ];
            } else {
                // Expired — clear it
                $player->update(['switch_request_from' => null, 'switch_requested_at' => null]);
            }
        }


        $genderLabel = null;
        if ($round) {
            $genderLabel = $round->gender_mode === 'male' ? '🕺 Mard Round' : '💃 Aurat Round';
        }

        if (!$round) {
            return response()->json([
                'game_status'    => $game->status,
                'round_status'   => null,
                'player_name'    => $player->name,
                'gender_mode'    => null,
                'gender_label'   => null,
                'task'           => null,
                'tasks_done'     => 0,
                'all_mine_done'  => false,
                'total_tasks'    => 0,
                'done_tasks'     => 0,
                'switch_request' => $switchRequest,
                'message'        => $game->status === 'completed'
                    ? 'Game khatam! Shukriya 🎊'
                    : 'Admin ka intezaar karo... 🛋️',
            ]);
        }

        $task         = $this->engine->getPlayerCurrentTask($round, $player->id);
        $tasksDone    = $this->engine->getPlayerTasksDone($round, $player->id);
        $allMineDone  = $task === null && $round->status === 'step1';
        $totalTasks   = $this->engine->getTotalTasks($round);
        $doneTasks    = $this->engine->getSubmittedCount($round);

        $stateHash = md5($round->status . '|' . ($task ? json_encode($task) : 'null') . '|' . $tasksDone . '|' . $doneTasks);

        return response()->json([
            'game_status'    => $game->status,
            'round_id'       => $round->id,
            'round_number'   => $round->round_number,
            'round_status'   => $round->status,
            'gender_mode'    => $round->gender_mode,
            'gender_label'   => $genderLabel,
            'player_name'    => $player->name,
            'task'           => $task,
            'tasks_done'     => $tasksDone,
            'all_mine_done'  => $allMineDone,
            'total_tasks'    => $totalTasks,
            'done_tasks'     => $doneTasks,
            'switch_request' => $switchRequest,
            'state_hash'     => $stateHash,
        ]);
    }

    // -------------------------------------------------------
    // ADMIN STATE — polled every 0.6s
    // -------------------------------------------------------
    public function adminState(Request $request, string $code): JsonResponse
    {
        if (!session()->has('gs_admin_id')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $game  = GsGame::where('room_code', $code)->with('players')->firstOrFail();
        $round = $game->currentRound();

        $playerStatuses = $game->players->map(function (GsPlayer $player) use ($round) {
            $step1Done = false;
            $step2Done = false;
            $step3Done = false;

            if ($round) {
                $c1 = $round->chits()->where('step1_player_id', $player->id)->first();
                $c2 = $round->chits()->where('step2_player_id', $player->id)->first();
                $c3 = $round->chits()->where('step3_player_id', $player->id)->first();
                $step1Done = $c1 && $c1->name_val !== null;
                $step2Done = $c2 && $c2->action_val !== null;
                $step3Done = $c3 && $c3->location_val !== null;
            }

            return [
                'id'         => $player->id,
                'name'       => $player->name,
                'is_admin'   => $player->is_admin,
                'is_online'  => $player->isOnline(),
                'step1_done' => $step1Done,
                'step2_done' => $step2Done,
                'step3_done' => $step3Done,
            ];
        });

        $genderLabel   = null;
        $totalTasks    = 0;
        $doneTasks     = 0;
        if ($round) {
            $genderLabel = $round->gender_mode === 'male' ? '🕺 Mard Round' : '💃 Aurat Round';
            $totalTasks  = $this->engine->getTotalTasks($round);
            $doneTasks   = $this->engine->getSubmittedCount($round);
        }

        $allComplete   = $round && $round->status === 'reveal';
        $revealedCount = $round ? $round->chits()->where('is_revealed', true)->count() : 0;

        // ---- Admin's own player task (if admin also playing) ----
        $adminPlayerTask      = null;
        $adminPlayerTasksDone = 0;
        $adminPlayerAllDone   = false;
        if ($game->admin_player_id && $round) {
            $adminPlayer = GsPlayer::find($game->admin_player_id);
            if ($adminPlayer) {
                // Heartbeat — keeps admin player's dot green
                $adminPlayer->update(['last_seen_at' => now()]);
                $adminPlayerTask      = $this->engine->getPlayerCurrentTask($round, $adminPlayer->id);
                $adminPlayerTasksDone = $this->engine->getPlayerTasksDone($round, $adminPlayer->id);
                $adminPlayerAllDone   = ($adminPlayerTask === null);
            }
        }

        $stateHash = md5(
            ($round ? $round->status : 'null') . '|' .
            $doneTasks . '|' .
            ($adminPlayerTask ? $adminPlayerTask['step'] : 'null') . '|' .
            $adminPlayerTasksDone
        );

        return response()->json([
            'game_status'              => $game->status,
            'round_id'                 => $round ? $round->id : null,
            'round_number'             => $round ? $round->round_number : null,
            'round_status'             => $round ? $round->status : null,
            'gender_mode'              => $round ? $round->gender_mode : null,
            'gender_label'             => $genderLabel,
            'players'                  => $playerStatuses,
            'all_complete'             => $allComplete,
            'total_tasks'              => $totalTasks,
            'done_tasks'               => $doneTasks,
            'revealed_count'           => $revealedCount,
            'admin_player_task'        => $adminPlayerTask,
            'admin_player_tasks_done'  => $adminPlayerTasksDone,
            'admin_player_all_done'    => $adminPlayerAllDone,
            'state_hash'               => $stateHash,
        ]);
    }


    // -------------------------------------------------------
    // HEARTBEAT
    // -------------------------------------------------------
    public function heartbeat(Request $request): JsonResponse
    {
        $token = session('gs_player_token');
        if ($token) {
            GsPlayer::where('session_token', $token)->update(['last_seen_at' => now()]);
        }
        return response()->json(['ok' => true]);
    }
}
