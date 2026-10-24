<?php

namespace App\Models\GossipSlip;

use Illuminate\Database\Eloquent\Model;

class GsGame extends Model
{
    protected $table = 'gs_games';

    protected $fillable = ['room_code', 'admin_id', 'admin_player_id', 'status'];

    public function admin()
    {
        return $this->belongsTo(GsAdmin::class, 'admin_id');
    }

    public function players()
    {
        return $this->hasMany(GsPlayer::class, 'game_id');
    }

    public function rounds()
    {
        return $this->hasMany(GsRound::class, 'game_id');
    }

    public function adminPlayer()
    {
        return $this->belongsTo(GsPlayer::class, 'admin_player_id');
    }

    /** Returns the active (non-finished) round, or null */
    public function currentRound(): ?GsRound
    {
        return $this->rounds()->whereNotIn('status', ['finished'])->latest()->first();
    }

    /** Returns the most recent round regardless of status */
    public function latestRound(): ?GsRound
    {
        return $this->rounds()->latest()->first();
    }

    public function nextRoundNumber(): int
    {
        return ($this->rounds()->max('round_number') ?? 0) + 1;
    }
}
