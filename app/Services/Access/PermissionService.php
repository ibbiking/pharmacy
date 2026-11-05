<?php

namespace App\Services\Access;

use App\Models\BusinessRole;
use App\Models\User;
use App\Services\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;

/**
 * Per-business roles & permissions (Roadmap 4i). Permission keys are declared by modules in
 * config/modules.php ('<module>.<area>.<action>'). A permission only counts while its module is on.
 * Hooked into Laravel's Gate (AppServiceProvider), so `$user->can('pos.sell')` works everywhere.
 */
class PermissionService
{
    /** @var array<string, ?BusinessRole> "userId:businessId" => role (per request) */
    private array $roles = [];

    public function __construct(private readonly ModuleManager $modules)
    {
    }

    /** @return array<string, array{module: string, name: string, permissions: array<string, array{0: string, 1: string}>}> */
    public function catalogue(): array
    {
        $out = [];
        foreach ($this->modules->all() as $key => $module) {
            if (! empty($module['permissions'])) {
                $out[$key] = ['module' => $key, 'name' => $module['name'], 'permissions' => $module['permissions']];
            }
        }

        return $out;
    }

    public function exists(string $permission): bool
    {
        $module = strtok($permission, '.');

        return isset($this->modules->all()[$module]['permissions'][$permission]);
    }

    public function allows(User $user, ?int $businessId, string $permission): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        if (! $businessId || ! $this->exists($permission)) {
            return false;
        }
        if (! $this->modules->isActive($businessId, strtok($permission, '.'))) {
            return false; // a switched-off module's permissions have no effect
        }

        $membership = DB::table('business_user')->where('business_id', $businessId)->where('user_id', $user->id)->first();
        if (! $membership || $membership->status !== 'active') {
            return false;
        }

        $role = $this->roleFor($user, $businessId);
        if (! $role) {
            return false;
        }

        return $role->isOwner() || in_array($permission, $role->permissionKeys(), true);
    }

    /** The user's role in a business: the assigned role, else the built-in role matching their membership. */
    public function roleFor(User $user, int $businessId): ?BusinessRole
    {
        $key = $user->id.':'.$businessId;
        if (! array_key_exists($key, $this->roles)) {
            $membership = DB::table('business_user')->where('business_id', $businessId)->where('user_id', $user->id)->first();
            $role = $membership?->business_role_id ? BusinessRole::query()->find($membership->business_role_id) : null;
            if (! $role && $membership) {
                $this->ensureSystemRoles($businessId);
                $role = BusinessRole::query()->where('business_id', $businessId)->where('system_key', $membership->role === 'owner' ? 'owner' : 'sales-person')->first();
            }
            $this->roles[$key] = $role;
        }

        return $this->roles[$key];
    }

    /** Role limits (e.g. max discount %) for POS rules; null = no extra limit. */
    public function limit(User $user, int $businessId, string $limit): mixed
    {
        $role = $this->roleFor($user, $businessId);

        return $role && ! $role->isOwner() ? ($role->limits[$limit] ?? null) : null;
    }

    /** Built-in Owner and Sales person roles for a business (idempotent). */
    public function ensureSystemRoles(int $businessId): void
    {
        foreach (config('modules.system_roles', []) as $systemKey => $def) {
            $role = BusinessRole::withTrashed()->firstOrCreate(
                ['business_id' => $businessId, 'system_key' => $systemKey],
                ['name' => $def['name'], 'description' => $def['description']],
            );
            if ($role->trashed()) {
                $role->restore();
            }
            if (is_array($def['permissions']) && $role->wasRecentlyCreated) {
                $role->syncPermissions($def['permissions']);
            }
        }
    }

    public function forget(): void
    {
        $this->roles = [];
    }
}
