<?php

namespace App\Models\GossipSlip;

use Illuminate\Database\Eloquent\Model;

class GsChit extends Model
{
    protected $table = 'gs_chits';

    protected $fillable = [
        'round_id', 'origin_player_id',
        'step1_player_id', 'step2_player_id', 'step3_player_id',
        'name_val', 'action_val', 'location_val', 'is_revealed',
    ];

    protected $casts = ['is_revealed' => 'boolean'];

    public function round()
    {
        return $this->belongsTo(GsRound::class, 'round_id');
    }

    public function originPlayer()
    {
        return $this->belongsTo(GsPlayer::class, 'origin_player_id');
    }

    public function step1Player()
    {
        return $this->belongsTo(GsPlayer::class, 'step1_player_id');
    }

    public function step2Player()
    {
        return $this->belongsTo(GsPlayer::class, 'step2_player_id');
    }

    public function step3Player()
    {
        return $this->belongsTo(GsPlayer::class, 'step3_player_id');
    }

    public function getRevealSentence(): string
    {
        $round    = $this->round;
        $genderMode = $round ? $round->gender_mode : 'male';
        $verb     = $genderMode === 'male' ? 'kar raha hai' : 'kar rahi hai';
        $name     = $this->name_val    ?? '???';
        $action   = $this->action_val  ?? '???';
        $location = $this->location_val ?? '???';
        return "{$name} — {$action} {$verb} — {$location} mein!";
    }

    public function isComplete(): bool
    {
        return $this->name_val !== null
            && $this->action_val !== null
            && $this->location_val !== null;
    }
}
