<?php

namespace App\Services\GossipSlip;

use App\Models\GossipSlip\GsChit;
use App\Models\GossipSlip\GsRound;
use Illuminate\Support\Collection;

class ChitEngine
{
    /**
     * Create N chits. ALL 3 step assignments pre-set at round start.
     * Players work independently — no waiting between steps.
     *
     *   chit[i].step1 = player[i]              (write name on own chit)
     *   chit[i].step2 = player[(i+1) % N]      (write action)
     *   chit[i].step3 = player[(i+S) % N]      (write location, S=2 for N>=3, else 1)
     */
    public function createChitsForRound(GsRound $round, Collection $players): void
    {
        $arr = $players->values()->all();
        $n   = count($arr);

        foreach ($arr as $i => $player) {
            GsChit::create([
                'round_id'         => $round->id,
                'origin_player_id' => $player->id,
                'step1_player_id'  => $player->id,
                'step2_player_id'  => $arr[($i + 1) % $n]->id,
                'step3_player_id'  => $arr[($i + ($n >= 3 ? 2 : 1)) % $n]->id,
            ]);
        }
    }

    /**
     * Load all 3 of a player's chits in ONE query.
     * Returns ['chit1' => ..., 'chit2' => ..., 'chit3' => ...].
     * This replaces 6 separate DB queries with a single OR query.
     */
    public function loadPlayerChits(GsRound $round, int $playerId): array
    {
        $chits = $round->chits()
            ->where(function ($q) use ($playerId) {
                $q->where('step1_player_id', $playerId)
                  ->orWhere('step2_player_id', $playerId)
                  ->orWhere('step3_player_id', $playerId);
            })
            ->limit(3)
            ->get();

        $c1 = null; $c2 = null; $c3 = null;
        foreach ($chits as $c) {
            if ((int) $c->step1_player_id === $playerId) $c1 = $c;
            if ((int) $c->step2_player_id === $playerId) $c2 = $c;
            if ((int) $c->step3_player_id === $playerId) $c3 = $c;
        }
        return ['chit1' => $c1, 'chit2' => $c2, 'chit3' => $c3];
    }

    /**
     * Returns both the current task AND tasks done — ONE query total.
     * Replaces getPlayerCurrentTask() + getPlayerTasksDone() combo (was 6 queries).
     */
    public function getPlayerState(GsRound $round, int $playerId): array
    {
        $loaded = $this->loadPlayerChits($round, $playerId);
        $c1     = $loaded['chit1'];
        $c2     = $loaded['chit2'];
        $c3     = $loaded['chit3'];
        $isMale = $round->gender_mode === 'male';

        $task      = null;
        $tasksDone = 0;

        // Step 1 — name on own chit
        if ($c1 && $c1->name_val === null) {
            $task = [
                'chit_id'     => $c1->id,
                'step'        => 'step1',
                'step_number' => 1,
                'label'       => 'Naam likho',
                'subtext'     => 'Kiska naam zehen mein aa raha hai? 🤔',
                'placeholder' => 'Kisi bhi insaan ka naam...',
                'emoji'       => '👤',
            ];
            return ['task' => $task, 'tasks_done' => $tasksDone, 'chits' => $loaded];
        }
        if ($c1 && $c1->name_val !== null) $tasksDone++;

        // Step 2 — action on someone else's chit
        if ($c2 && $c2->action_val === null) {
            $task = [
                'chit_id'     => $c2->id,
                'step'        => 'step2',
                'step_number' => 2,
                'label'       => $isMale ? 'Kya kar raha hai?' : 'Kya kar rahi hai?',
                'subtext'     => $isMale ? 'Banda kya harkat kar raha hai! 😏' : 'Wo kya kar rahi hai! 😏',
                'placeholder' => $isMale ? 'Kya kar raha hai...' : 'Kya kar rahi hai...',
                'emoji'       => $isMale ? '🕺' : '💃',
            ];
            return ['task' => $task, 'tasks_done' => $tasksDone, 'chits' => $loaded];
        }
        if ($c2 && $c2->action_val !== null) $tasksDone++;

        // Step 3 — location on yet another chit
        if ($c3 && $c3->location_val === null) {
            $task = [
                'chit_id'     => $c3->id,
                'step'        => 'step3',
                'step_number' => 3,
                'label'       => 'Kis jagha?',
                'subtext'     => 'Kahan pe chal raha hai ye poora scene? 📍',
                'placeholder' => 'Ghar, daftar, bazar, park...',
                'emoji'       => '📍',
            ];
            return ['task' => $task, 'tasks_done' => $tasksDone, 'chits' => $loaded];
        }
        if ($c3 && $c3->location_val !== null) $tasksDone++;

        return ['task' => null, 'tasks_done' => $tasksDone, 'chits' => $loaded];
    }

    /**
     * Backward-compat wrapper — delegates to getPlayerState (1 query).
     */
    public function getPlayerCurrentTask(GsRound $round, int $playerId): ?array
    {
        return $this->getPlayerState($round, $playerId)['task'];
    }

    /**
     * Backward-compat wrapper — delegates to getPlayerState (1 query).
     */
    public function getPlayerTasksDone(GsRound $round, int $playerId): int
    {
        return $this->getPlayerState($round, $playerId)['tasks_done'];
    }

    /**
     * Total completed tasks across ALL players — single SQL SUM.
     * Was: PHP loop (1 query + PHP iteration). Now: pure SQL.
     */
    public function getSubmittedCount(GsRound $round): int
    {
        $row = \DB::table('gs_chits')
            ->where('round_id', $round->id)
            ->selectRaw('
                SUM(name_val IS NOT NULL) +
                SUM(action_val IS NOT NULL) +
                SUM(location_val IS NOT NULL) AS total
            ')
            ->first();
        return $row ? (int) $row->total : 0;
    }

    /**
     * Total tasks = 3 × number of chits.
     */
    public function getTotalTasks(GsRound $round): int
    {
        return $round->chits()->count() * 3;
    }

    /**
     * Advance round to 'reveal' when every chit has all 3 fields filled.
     * No refresh() needed — status is passed in already loaded.
     */
    public function autoAdvanceIfReady(GsRound $round): void
    {
        if ($round->status !== 'step1') {
            return;
        }
        $incomplete = \DB::table('gs_chits')
            ->where('round_id', $round->id)
            ->where(function ($q) {
                $q->whereNull('name_val')
                  ->orWhereNull('action_val')
                  ->orWhereNull('location_val');
            })
            ->count();

        if ($incomplete === 0) {
            $round->update(['status' => 'reveal']);
            $round->status = 'reveal'; // update in-memory too
        }
    }

    /**
     * Check if all chits have a specific column filled (legacy helper).
     */
    public function checkAllSubmitted(GsRound $round, string $column): bool
    {
        return $round->chits()->whereNull($column)->count() === 0;
    }
}
