<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** A super admin of the whole platform (separate portal at /platform, guard `platform`). Not a business user. */
class PlatformAdmin extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'platform_admins';

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    /** Same default as the column, so a freshly created admin can sign in before it is reloaded. */
    protected $attributes = ['is_active' => true];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function canSignIn(): bool
    {
        return $this->is_active && ! $this->trashed();
    }
}
