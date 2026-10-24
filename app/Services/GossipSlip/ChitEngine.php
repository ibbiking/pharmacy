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
     * Returns the player's next pending task — strictly sequential per player.
     * Step 2 unlocks only after Step 1 done. Step 3 only after Step 2 done.
     * Returns null when all 3 tasks are complete.
     */
    public function getPlayerCurrentTask(GsRound $round, int $playerId): ?array
    {
        $isMale = $round->gender_mode === 'male';

        $chit1 = $round->chits()->where('step1_player_id', $playerId)->first();
        if ($chit1 && $chit1->name_val === null) {
            return [
                'chit_id'     => $chit1->id,
                'step'        => 'step1',
                'step_number' => 1,
                'label'       => 'Naam likho',
                'subtext'     => 'Kiska naam zehen mein aa raha hai? 🤔',
                'placeholder' => 'Kisi bhi insaan ka naam...',
                'emoji'       => '👤',
            ];
        }

        $chit2 = $round->chits()->where('step2_player_id', $playerId)->first();
        if ($chit2 && $chit2->action_val === null) {
            return [
                'chit_id'     => $chit2->id,
                'step'        => 'step2',
                'step_number' => 2,
                'label'       => $isMale ? 'Kya kar raha hai?' : 'Kya kar rahi hai?',
                'subtext'     => $isMale ? 'Banda kya harkat kar raha hai! 😏' : 'Wo kya kar rahi hai! 😏',
                'placeholder' => $isMale ? 'Kya kar raha hai...' : 'Kya kar rahi hai...',
                'emoji'       => $isMale ? '🕺' : '💃',
            ];
        }

        $chit3 = $round->chits()->where('step3_player_id', $playerId)->first();
        if ($chit3 && $chit3->location_val === null) {
            return [
                'chit_id'     => $chit3->id,
                'step'        => 'step3',
                'step_number' => 3,
                'label'       => 'Kis jagha?',
                'subtext'     => 'Kahan pe chal raha hai ye poora scene? 📍',
                'placeholder' => 'Ghar, daftar, bazar, masjid...',
                'emoji'       => '📍',
            ];
        }

        return null; // all 3 done
    }

    /**
     * How many of this player's 3 tasks are done.
     */
    public function getPlayerTasksDone(GsRound $round, int $playerId): int
    {
        $done = 0;
        $c1   = $round->chits()->where('step1_player_id', $playerId)->first();
        $c2   = $round->chits()->where('step2_player_id', $playerId)->first();
        $c3   = $round->chits()->where('step3_player_id', $playerId)->first();
        if ($c1 && $c1->name_val !== null)     $done++;
        if ($c2 && $c2->action_val !== null)   $done++;
        if ($c3 && $c3->location_val !== null) $done++;
        return $done;
    }

    /**
     * Total completed tasks across ALL players (for global progress bar).
     */
    public function getSubmittedCount(GsRound $round): int
    {
        $done = 0;
        foreach ($round->chits()->get() as $chit) {
            if ($chit->name_val !== null)     $done++;
            if ($chit->action_val !== null)   $done++;
            if ($chit->location_val !== null) $done++;
        }
        return $done;
    }

    /**
     * Total tasks in this round = 3 × number of players.
     */
    public function getTotalTasks(GsRound $round): int
    {
        return $round->chits()->count() * 3;
    }

    /**
     * Check if all fields of a given column are submitted (legacy).
     */
    public function checkAllSubmitted(GsRound $round, string $column): bool
    {
        return $round->chits()->whereNull($column)->count() === 0;
    }

    /**
     * Advance round to 'reveal' when every chit has all 3 fields filled.
     */
    public function autoAdvanceIfReady(GsRound $round): void
    {
        $round->refresh();
        if ($round->status !== 'step1') {
            return;
        }
        $incomplete = $round->chits()
            ->where(function ($q) {
                $q->whereNull('name_val')
                  ->orWhereNull('action_val')
                  ->orWhereNull('location_val');
            })
            ->count();

        if ($incomplete === 0) {
            $round->update(['status' => 'reveal']);
        }
    }
}
