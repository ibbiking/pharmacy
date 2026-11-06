<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessRole;
use App\Services\Access\PermissionService;
use App\Services\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Settings → Roles (Roadmap 4i-2): the business's roles and their permission matrix. */
class RoleManagementController extends Controller
{
    public function __construct(private readonly PermissionService $permissions, private readonly ModuleManager $modules)
    {
    }

    public function index(Request $request)
    {
        $businessId = $this->authorizeManage($request);
        $this->permissions->ensureSystemRoles($businessId);
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;

        $roles = BusinessRole::query()->where('business_id', $businessId)
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderByRaw("system_key = 'owner' desc, system_key is null, name")
            ->paginate($perPage)->withQueryString()
            ->through(fn (BusinessRole $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'system_key' => $r->system_key,
                'limits' => $r->limits ?? (object) [],
                'permissions' => $r->isOwner() ? ['*'] : $r->permissionKeys(),
                // Members linked to the role, plus (for built-in roles) members without a role id whose
                // membership falls back to it (see PermissionService::roleFor()).
                'members' => DB::table('business_user')->where('business_id', $businessId)
                    ->where(fn ($w) => $w->where('business_role_id', $r->id)
                        ->when($r->system_key, fn ($w2) => $w2->orWhere(fn ($w3) => $w3->whereNull('business_role_id')
                            ->where('role', $r->system_key === 'owner' ? '=' : '!=', 'owner'))))
                    ->count(),
            ]);

        $active = $this->modules->activeFor($businessId);

        return Inertia::render('Settings/Roles', [
            'roles' => $roles,
            'filters' => ['q' => $q, 'per_page' => $perPage],
            'catalogue' => collect($this->permissions->catalogue())->map(fn ($m) => [
                'module' => $m['module'],
                'name' => $m['name'],
                'active' => in_array($m['module'], $active, true),
                'permissions' => collect($m['permissions'])->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'description' => $p[1]])->values(),
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $businessId = $this->authorizeManage($request);
        $data = $this->validated($request, $businessId);
        if ($error = $this->escalation($request, $businessId, null, $data['permissions'])) {
            return back()->with('error', $error);
        }

        $role = BusinessRole::create(['business_id' => $businessId, 'name' => $data['name'], 'description' => $data['description'] ?? null, 'limits' => $data['limits']]);
        $role->syncPermissions($data['permissions']);

        return back()->with('success', "Role “{$role->name}” created.");
    }

    public function update(Request $request, BusinessRole $role)
    {
        $businessId = $this->authorizeManage($request);
        abort_unless($role->business_id === $businessId, 404);
        abort_if($role->isOwner(), 403, 'The Owner role always has everything.');

        $data = $this->validated($request, $businessId, $role);
        if ($error = $this->escalation($request, $businessId, $role, $data['permissions'])) {
            return back()->with('error', $error);
        }
        $role->update([
            // Built-in roles keep their name.
            'name' => $role->system_key ? $role->name : $data['name'],
            'description' => $data['description'] ?? null,
            'limits' => $data['limits'],
        ]);
        $role->syncPermissions($data['permissions']);

        return back()->with('success', "Role “{$role->name}” saved.");
    }

    public function destroy(Request $request, BusinessRole $role)
    {
        $businessId = $this->authorizeManage($request);
        abort_unless($role->business_id === $businessId, 404);
        if ($role->system_key) {
            return back()->with('error', 'Built-in roles cannot be archived.');
        }
        if (DB::table('business_user')->where('business_role_id', $role->id)->exists()) {
            return back()->with('error', "Give the members of “{$role->name}” another role first.");
        }

        $role->delete();

        return back()->with('success', "Role “{$role->name}” archived.");
    }

    /**
     * Security review 13e: a manager who is not an owner can't edit the role they hold themselves, and can only hand out
     * permissions they already have (otherwise they could grant themselves billing or settings).
     */
    private function escalation(Request $request, int $businessId, ?BusinessRole $role, array $permissions): ?string
    {
        $user = $request->user();
        $own = $this->permissions->roleFor($user, $businessId);
        if ($user->hasRole('super-admin') || $own?->isOwner()) {
            return null;
        }
        if ($role && $own && $own->id === $role->id) {
            return 'You can’t change the role you have yourself. Ask an owner.';
        }
        // Permissions the role already had may stay; only NEW ones must be the manager's own.
        $extra = array_values(array_diff($permissions, $own?->permissionKeys() ?? [], $role?->permissionKeys() ?? []));

        return $extra ? 'You can only give permissions you have yourself. Ask an owner for: '.implode(', ', $extra).'.' : null;
    }

    private function validated(Request $request, int $businessId, ?BusinessRole $role = null): array
    {
        $keys = collect($this->permissions->catalogue())->flatMap(fn ($m) => array_keys($m['permissions']))->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('business_roles', 'name')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($keys)],
            'limits' => ['nullable', 'array'],
            'limits.max_discount_percent' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $data['permissions'] ??= [];
        $max = $data['limits']['max_discount_percent'] ?? null;
        $data['limits'] = $max === null || $max === '' ? null : ['max_discount_percent' => (float) $max];

        return $data;
    }

    private function authorizeManage(Request $request): int
    {
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        abort_unless($request->user()->can('core.users.manage'), 403);

        return $businessId;
    }
}
