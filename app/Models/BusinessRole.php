<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A role inside one business (Owner, Sales person, or a custom role made by the owner). */
class BusinessRole extends Model
{
    use SoftDeletes;
    use \App\Traits\Auditable;

    protected $guarded = [];
    protected $casts = ['limits' => 'array'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /** @return string[] */
    public function permissionKeys(): array
    {
        return \Illuminate\Support\Facades\DB::table('business_role_permissions')->where('business_role_id', $this->id)->pluck('permission')->all();
    }

    public function syncPermissions(array $keys): void
    {
        \Illuminate\Support\Facades\DB::table('business_role_permissions')->where('business_role_id', $this->id)->delete();
        foreach (array_unique($keys) as $key) {
            \Illuminate\Support\Facades\DB::table('business_role_permissions')->insert(['business_role_id' => $this->id, 'permission' => $key]);
        }
    }

    public function isOwner(): bool
    {
        return $this->system_key === 'owner';
    }
}
