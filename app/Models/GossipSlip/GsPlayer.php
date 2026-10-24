<?php

namespace App\Models\GossipSlip;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GsPlayer extends Model
{
    protected $table = 'gs_players';

    protected $fillable = [
        'game_id', 'name', 'session_token', 'is_admin',
        'last_seen_at', 'switch_request_from', 'switch_requested_at',
    ];

    protected $casts = [
        'last_seen_at'        => 'datetime',
        'switch_requested_at' => 'datetime',
        'is_admin'            => 'boolean',
    ];

    public function game()
    {
        return $this->belongsTo(GsGame::class, 'game_id');
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subSeconds(15));
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }
}
