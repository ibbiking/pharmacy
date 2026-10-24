<?php

namespace App\Models\GossipSlip;

use Illuminate\Database\Eloquent\Model;

class GsRound extends Model
{
    protected $table = 'gs_rounds';

    protected $fillable = ['game_id', 'round_number', 'gender_mode', 'status'];

    public function game()
    {
        return $this->belongsTo(GsGame::class, 'game_id');
    }

    public function chits()
    {
        return $this->hasMany(GsChit::class, 'round_id');
    }

    public function getStepLabel(): string
    {
        switch ($this->status) {
            case 'step1':  return 'Naam';
            case 'step2':  return $this->gender_mode === 'male' ? 'Kya kar raha hai?' : 'Kya kar rahi hai?';
            case 'step3':  return 'Kis jagha?';
            case 'reveal': return '🎭 Reveal Time!';
            default:       return 'Khatam!';
        }
    }

    public function getStepSubtext(): string
    {
        switch ($this->status) {
            case 'step1':  return 'Kiska naam zehen mein aa raha hai? 🤔';
            case 'step2':  return $this->gender_mode === 'male'
                               ? 'Batao banda kya harkat kar raha hai! 😏'
                               : 'Batao wo kya kar rahi hai! 😏';
            case 'step3':  return 'Kahan pe chal raha hai ye poora scene? 📍';
            default:       return '';
        }
    }

    public function getStepNumber(): int
    {
        switch ($this->status) {
            case 'step1': return 1;
            case 'step2': return 2;
            case 'step3': return 3;
            default:      return 0;
        }
    }
}
