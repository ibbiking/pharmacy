<?php

namespace App\Models\GossipSlip;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class GsAdmin extends Model
{
    protected $table = 'gs_admins';

    protected $fillable = ['display_name', 'username', 'password'];

    protected $hidden = ['password'];

    public function games()
    {
        return $this->hasMany(GsGame::class, 'admin_id');
    }

    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->password);
    }
}
