<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable,HasRoles;
    // Removing a user archives the account (history stays intact). Its email stays reserved.
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /** This user's role inside a business: 'owner', 'sales-person', … or null when not a member. */
    public function roleIn(?int $businessId): ?string
    {
        if (! $businessId) {
            return null;
        }

        return $this->businesses()->whereKey($businessId)->first()?->pivot?->role;
    }

    /** Owner of the business (super-admins count as owners everywhere). */
    public function isOwnerOf(?int $businessId): bool
    {
        return $this->hasRole('super-admin') || $this->roleIn($businessId) === 'owner';
    }

    /**
     * Sales-person in the current business. Roles are per business: someone who sells in one shop can
     * own another. Without a business context, falls back to the global role.
     */
    public function isSalesPersonIn(?int $businessId): bool
    {
        if ($this->hasRole('super-admin')) {
            return false;
        }
        $role = $this->roleIn($businessId);

        return $role !== null ? $role === 'sales-person' : $this->hasRole('sales-person');
    }

    public function businesses()
    {
        return $this->belongsToMany(Business::class)->withPivot('role', 'status')->withTimestamps();
    }

    /** Memberships that may be used (security review 13e: a suspended member can't enter or switch to the business). */
    public function activeBusinesses()
    {
        return $this->businesses()->wherePivot('status', 'active');
    }

    public function currentBusinessRole()
    {
        if (!session()->has('business_id')) return null;
        $business = $this->businesses()->where('business_id', session('business_id'))->first();
        return $business ? $business->pivot->role : null;
    }

    public function hasBusinessRole($role)
    {
        return $this->currentBusinessRole() === $role;
    }
}
