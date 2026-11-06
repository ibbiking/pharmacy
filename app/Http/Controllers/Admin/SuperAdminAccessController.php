<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/**
 * Super admin, read-only (Roadmap 5.11b): every user on the platform and the global Spatie roles.
 * Replaces the legacy Users / Roles / Permissions screens (SA-1: a business owner could edit any user there, including
 * giving themselves the global super-admin role). People are managed per business in Settings → Team and Roles (4i).
 */
class SuperAdminAccessController extends Controller
{
    private const SORTABLE = ['name', 'email', 'created_at', 'business_count'];
    private const PER_PAGE = [10, 20, 50];

    public function users(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'sort' => in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'created_at',
            'dir' => $request->input('dir') === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20,
            'kind' => in_array($request->input('kind'), ['owners', 'staff', 'none', 'unverified'], true) ? $request->input('kind') : '',
        ];
        $memberships = DB::table('business_user')->selectRaw('count(*)')->whereColumn('business_user.user_id', 'users.id');

        $rows = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.email_verified_at', 'users.created_at')
            ->selectSub($memberships, 'business_count')
            ->with(['roles:id,name', 'businesses' => fn ($q) => $q->select('businesses.id', 'businesses.name')->limit(50)])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('users.name', 'like', "%{$filters['q']}%")->orWhere('users.email', 'like', "%{$filters['q']}%")))
            ->when($filters['kind'] !== '', fn ($q) => $this->whereKind($q, $filters['kind']))
            ->orderBy($filters['sort'], $filters['dir'])->orderBy('users.id')
            ->paginate($filters['per_page'])->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => $u->avatar ? asset('storage/users/'.$u->avatar) : null,
                'verified' => (bool) $u->email_verified_at,
                'super_admin' => $u->roles->contains('name', 'super-admin'),
                'businesses' => (int) $u->business_count,
                'memberships' => $u->businesses->take(3)->map(fn ($b) => ['name' => $b->name, 'role' => $b->pivot->role])->values(),
                'created_at' => $u->created_at?->format('d M Y'),
            ]);

        return Inertia::render('SuperAdmin/Users', [
            'users' => $rows,
            'filters' => $filters,
            'stats' => fn () => [
                'count' => User::count(),
                'owners' => $this->whereKind(User::query(), 'owners')->count(),
                'staff' => $this->whereKind(User::query(), 'staff')->count(),
                'none' => $this->whereKind(User::query(), 'none')->count(),
                'unverified' => $this->whereKind(User::query(), 'unverified')->count(),
            ],
        ]);
    }

    private function whereKind($query, string $kind)
    {
        $member = fn (?string $role) => fn ($e) => $e->from('business_user')->whereColumn('business_user.user_id', 'users.id')
            ->when($role === 'owner', fn ($w) => $w->where('business_user.role', 'owner'))
            ->when($role === 'staff', fn ($w) => $w->where('business_user.role', '!=', 'owner'));

        return match ($kind) {
            'owners' => $query->whereExists($member('owner')),
            'staff' => $query->whereExists($member('staff'))->whereNotExists($member('owner')),
            'none' => $query->whereNotExists($member(null)),
            'unverified' => $query->whereNull('users.email_verified_at'),
        };
    }

    public function roles()
    {
        $roles = Role::query()->withCount(['permissions', 'users'])->with('permissions:id,name')->orderBy('name')->paginate(20)
            ->through(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'users' => $r->users_count,
                'permissions' => $r->permissions_count,
                'sample' => $r->permissions->pluck('name')->sort()->take(12)->values(),
            ]);

        return Inertia::render('SuperAdmin/Roles', ['roles' => $roles]);
    }
}
