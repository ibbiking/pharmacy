<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Super admin: every business on the platform (Roadmap 5.11b). Read-only list + "open as owner" (impersonation).
 * SA-4: impersonation is a POST (a plain link can no longer switch a super admin into a business).
 * Counts use plain query builders pinned by business_id, so the BusinessScope of an impersonated business never applies.
 */
class SuperAdminBusinessController extends Controller
{
    private const SORTABLE = ['name', 'created_at', 'last_sale_at', 'members'];
    private const PER_PAGE = [10, 20, 50];

    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'sort' => in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'created_at',
            'dir' => $request->input('dir') === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20,
            'status' => in_array($request->input('status'), ['active', 'idle', 'new'], true) ? $request->input('status') : '',
        ];
        $since = now()->subDays(30);
        $lastSale = DB::table('invoices')->selectRaw('max(invoices.created_at)')->whereColumn('invoices.business_id', 'businesses.id')->whereNull('invoices.deleted_at');
        $members = DB::table('business_user')->selectRaw('count(*)')->whereColumn('business_user.business_id', 'businesses.id');
        $owner = fn (string $col) => DB::table('business_user')->join('users', 'users.id', '=', 'business_user.user_id')
            ->whereColumn('business_user.business_id', 'businesses.id')->where('business_user.role', 'owner')->orderBy('business_user.id')->limit(1)->select("users.$col");

        $rows = Business::query()
            ->select('businesses.id', 'businesses.name', 'businesses.business_type', 'businesses.created_at')
            ->selectSub($lastSale, 'last_sale_at')->selectSub($members, 'members')
            ->selectSub($owner('name'), 'owner_name')->selectSub($owner('email'), 'owner_email')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('businesses.name', 'like', "%{$filters['q']}%")
                ->orWhereExists(fn ($e) => $e->from('business_user')->join('users', 'users.id', '=', 'business_user.user_id')
                    ->whereColumn('business_user.business_id', 'businesses.id')->where('users.email', 'like', "%{$filters['q']}%"))))
            ->when($filters['status'] !== '', fn ($q) => $this->whereStatus($q, $filters['status'], $since))
            ->orderBy($filters['sort'], $filters['dir'])->orderBy('businesses.id')
            ->paginate($filters['per_page'])->withQueryString()
            ->through(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'type' => $b->business_type,
                'owner' => $b->owner_name ? ['name' => $b->owner_name, 'email' => $b->owner_email] : null,
                'members' => (int) $b->members,
                'last_sale_at' => $b->last_sale_at ? \Illuminate\Support\Carbon::parse($b->last_sale_at)->format('d M Y') : null,
                'created_at' => $b->created_at?->format('d M Y'),
            ]);

        return Inertia::render('SuperAdmin/Businesses', [
            'businesses' => $rows,
            'filters' => $filters,
            'stats' => fn () => [
                'count' => Business::count(),
                'active' => $this->whereStatus(Business::query(), 'active', $since)->count(),
                'idle' => $this->whereStatus(Business::query(), 'idle', $since)->count(),
                'new' => $this->whereStatus(Business::query(), 'new', $since)->count(),
            ],
        ]);
    }

    /** active = sold in the last 30 days; idle = no sale in that time; new = created in the last 30 days. */
    private function whereStatus($query, string $status, $since)
    {
        $sold = fn ($e) => $e->from('invoices')->whereColumn('invoices.business_id', 'businesses.id')->whereNull('invoices.deleted_at')->where('invoices.created_at', '>=', $since);

        return match ($status) {
            'active' => $query->whereExists($sold),
            'idle' => $query->whereNotExists($sold),
            'new' => $query->where('businesses.created_at', '>=', $since),
        };
    }

    /** One business: who runs it, what it holds, and every module / sub-module with its switch and block. */
    public function show(int $id, \App\Services\Modules\ModuleManager $modules)
    {
        $business = Business::query()->findOrFail($id);
        $modules->forget($business->id);
        $count = fn (string $table) => \Illuminate\Support\Facades\Schema::hasTable($table)
            ? DB::table($table)->where('business_id', $business->id)->when(\Illuminate\Support\Facades\Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->count() : 0;
        $members = DB::table('business_user')->join('users', 'users.id', '=', 'business_user.user_id')
            ->leftJoin('business_roles', 'business_roles.id', '=', 'business_user.business_role_id')
            ->where('business_user.business_id', $business->id)->orderByRaw("business_user.role = 'owner' desc")->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email', 'business_user.role', 'business_user.status', 'business_roles.name as role_name'])
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'role' => $m->role_name ?? ucfirst(str_replace('-', ' ', (string) $m->role)), 'owner' => $m->role === 'owner', 'status' => $m->status]);

        $active = $modules->activeFor($business->id);
        $blocked = $modules->blockedFor($business->id);
        $list = [];
        foreach ($modules->all() as $key => $m) {
            $allowed = $modules->isAllowed($business->business_type, $key);
            if (! $allowed && ! in_array($key, $active, true)) {
                continue; // another type's module: never offered to this business
            }
            $list[] = [
                'key' => $key, 'name' => $m['name'], 'description' => $m['description'] ?? '', 'icon' => $m['icon'] ?? 'puzzle', 'accent' => $m['accent'] ?? 'var(--chart-1)',
                'core' => (bool) ($m['core'] ?? false), 'available' => (bool) ($m['available'] ?? true),
                'locked' => $modules->isLocked($business->business_type, $key),
                'active' => in_array($key, $active, true), 'blocked' => in_array($key, $blocked, true),
                'subs' => collect($m['permissions'] ?? [])->map(fn ($p, $pk) => ['key' => $pk, 'name' => $p[0], 'description' => $p[1] ?? '', 'blocked' => in_array($pk, $blocked, true)])->values(),
            ];
        }

        return Inertia::render('SuperAdmin/BusinessShow', [
            'business' => [
                'id' => $business->id, 'name' => $business->name, 'type' => $business->business_type, 'type_name' => $modules->typeName($business->business_type),
                'phone' => $business->phone, 'address' => $business->address, 'created_at' => $business->created_at?->format('d M Y'),
            ],
            'members' => $members,
            'counts' => [
                'products' => $count('products'), 'sales' => $count('invoices'), 'customers' => $count('customers'),
                'suppliers' => $count('suppliers'), 'documents' => $count('documents'), 'expenses' => $count('expenses'),
            ],
            'modules' => $list,
        ]);
    }

    /** Switch a module on / off for a business (same rules as the owner's Modules page). */
    public function toggleModule(Request $request, int $id, string $module, \App\Services\Modules\ModuleManager $modules)
    {
        $business = Business::query()->findOrFail($id);
        abort_unless($modules->exists($module), 404);
        $on = $request->boolean('on');
        try {
            $on ? $modules->activate($business, $module, 'platform', $request->user()->id) : $modules->deactivate($business, $module);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $modules->definition($module)['name'].($on ? ' switched on.' : ' switched off.'));
    }

    /** Block or unblock a module or a sub-module (permission key) for a business. */
    public function block(Request $request, int $id, \App\Services\Modules\ModuleManager $modules, \App\Services\Access\PermissionService $permissions)
    {
        $business = Business::query()->findOrFail($id);
        $data = $request->validate(['key' => ['required', 'string', 'max:120'], 'blocked' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:255']]);
        $key = $data['key'];
        $isModule = $modules->exists($key);
        abort_unless($isModule || $permissions->exists($key), 422, 'Unknown module or sub-module.');
        if ($isModule && ($modules->definition($key)['core'] ?? false)) {
            return back()->with('error', 'Core can’t be blocked as a whole: block its sub-modules instead.');
        }
        $name = $isModule ? $modules->definition($key)['name'] : ($permissions->catalogue()[strtok($key, '.')]['permissions'][$key][0] ?? $key);

        if ($data['blocked']) {
            \App\Models\BusinessAccessBlock::query()->updateOrCreate(['business_id' => $business->id, 'key' => $key], ['reason' => $data['reason'] ?? null, 'blocked_by' => $request->user()->id]);
        } else {
            \App\Models\BusinessAccessBlock::query()->where('business_id', $business->id)->where('key', $key)->delete();
        }
        $modules->forget($business->id);
        $permissions->forget();

        return back()->with('success', "{$name} ".($data['blocked'] ? 'blocked' : 'unblocked')." for {$business->name}.");
    }

    public function impersonate(int $id)
    {
        $business = Business::findOrFail($id);
        session(['impersonate_business_id' => $business->id, 'business_id' => $business->id]);
        session()->forget('pos_cart');

        return redirect()->route('dashboard')->with('success', "You are now working inside {$business->name}.");
    }

    public function stopImpersonating()
    {
        session()->forget(['impersonate_business_id', 'business_id', 'pos_cart']);

        return redirect()->route('superadmin.businesses')->with('success', 'Back to the super admin view.');
    }
}
