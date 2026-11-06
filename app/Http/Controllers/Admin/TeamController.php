<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBusinessMail;
use App\Mail\BusinessMessage;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\User;
use App\Services\Access\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Settings → Team (Roadmap 4i-3): who works in this business and with which role. */
class TeamController extends Controller
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function index(Request $request)
    {
        $business = $this->authorizeManage($request);
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;

        $members = DB::table('business_user as bu')
            ->join('users as u', 'u.id', '=', 'bu.user_id')
            ->where('bu.business_id', $business->id)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('u.name', 'like', "%{$q}%")->orWhere('u.email', 'like', "%{$q}%")))
            ->orderByRaw("bu.role = 'owner' desc")->orderBy('u.name')
            ->select('u.id', 'u.name', 'u.email', 'bu.role', 'bu.business_role_id', 'bu.status', 'bu.created_at')
            ->paginate($perPage)->withQueryString()
            ->through(function ($m) use ($business) {
                $role = $this->permissions->roleFor(User::find($m->id), $business->id);

                return [
                    'id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'status' => $m->status,
                    'role_id' => $role?->id, 'role_name' => $role?->name, 'is_owner' => (bool) $role?->isOwner(),
                    'joined' => $m->created_at ? \Illuminate\Support\Carbon::parse($m->created_at)->format('d M Y') : null,
                    'is_me' => $m->id === auth()->id(),
                ];
            });

        $counts = DB::table('business_user')->where('business_id', $business->id)->selectRaw("count(*) as total, sum(status = 'active') as active, sum(status = 'suspended') as suspended")->first();

        return Inertia::render('Settings/Team', [
            'members' => $members,
            'filters' => ['q' => $q, 'per_page' => $perPage],
            'roles' => BusinessRole::query()->where('business_id', $business->id)->orderByRaw("system_key = 'owner' desc, name")->get(['id', 'name', 'system_key']),
            'stats' => ['total' => (int) $counts->total, 'active' => (int) $counts->active, 'suspended' => (int) $counts->suspended],
        ]);
    }

    public function invite(Request $request)
    {
        $business = $this->authorizeManage($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'role_id' => ['required', Rule::exists('business_roles', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
        ]);

        $data['email'] = \Illuminate\Support\Str::lower(trim($data['email']));
        $user = User::withTrashed()->where('email', $data['email'])->first();
        if ($user?->trashed()) {
            return back()->withErrors(['email' => 'This email belongs to an archived account. Ask support to restore it first.']);
        }
        if ($user && $user->businesses()->whereKey($business->id)->exists()) {
            return back()->withErrors(['email' => 'This person is already in the team.']);
        }

        // Security review 13e: only an owner can make someone an owner.
        if (BusinessRole::findOrFail($data['role_id'])->isOwner() && ! $this->actorIsOwner($request, $business)) {
            return back()->withErrors(['role_id' => 'Only an owner can add another owner.']);
        }

        $isNew = ! $user;
        $user ??= User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make(Str::random(40))]);
        $role = BusinessRole::findOrFail($data['role_id']);
        $user->businesses()->attach($business->id, ['role' => $this->pivotRole($role), 'business_role_id' => $role->id, 'status' => 'active']);

        SendBusinessMail::dispatch($business, $user->email, new BusinessMessage(
            $business,
            "You've been added to {$business->name}",
            "Welcome to {$business->name}",
            array_filter([
                "{$request->user()->name} added you to {$business->name} as {$role->name}.",
                $isNew ? 'Your account is ready. To choose your password, open the sign-in page and click “Forgot password”.' : 'Sign in with your existing account; the new business appears in your business menu.',
            ]),
            [['Business', $business->name], ['Role', $role->name], ['Email', $user->email]],
            ['Sign in', route('login')],
        ));

        return back()->with('success', "{$user->name} added as {$role->name}. An invitation email is on its way.");
    }

    public function update(Request $request, User $member)
    {
        $business = $this->authorizeManage($request);
        $this->assertMember($business, $member);
        $data = $request->validate([
            'role_id' => ['sometimes', Rule::exists('business_roles', 'id')->where('business_id', $business->id)->whereNull('deleted_at')],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);
        if ($member->id === $request->user()->id) {
            return back()->with('error', 'You cannot change your own role or status.');
        }

        $currentRole = $this->permissions->roleFor($member, $business->id);
        $newRole = isset($data['role_id']) ? BusinessRole::findOrFail($data['role_id']) : $currentRole;
        $newStatus = $data['status'] ?? DB::table('business_user')->where('business_id', $business->id)->where('user_id', $member->id)->value('status');

        if (($currentRole?->isOwner() || $newRole->isOwner()) && ! $this->actorIsOwner($request, $business)) {
            return back()->with('error', 'Only an owner can change an owner, or make someone an owner.');
        }
        if ($currentRole?->isOwner() && (! $newRole->isOwner() || $newStatus !== 'active') && $this->activeOwners($business) <= 1) {
            return back()->with('error', 'A business needs at least one active owner.');
        }

        $member->businesses()->updateExistingPivot($business->id, [
            'business_role_id' => $newRole->id, 'role' => $this->pivotRole($newRole), 'status' => $newStatus,
        ]);
        app(\App\Services\Audit\AuditLogger::class)->record('member_updated', $member, [
            'role' => ['from' => $currentRole?->name, 'to' => $newRole->name], 'status' => ['to' => $newStatus],
        ], "{$member->name}: {$newRole->name}, {$newStatus}");

        return back()->with('success', "{$member->name} updated.");
    }

    public function remove(Request $request, User $member)
    {
        $business = $this->authorizeManage($request);
        $this->assertMember($business, $member);
        if ($member->id === $request->user()->id) {
            return back()->with('error', 'You cannot remove yourself.');
        }

        if ($this->permissions->roleFor($member, $business->id)?->isOwner() && ! $this->actorIsOwner($request, $business)) {
            return back()->with('error', 'Only an owner can remove an owner.');
        }
        if ($this->permissions->roleFor($member, $business->id)?->isOwner() && $this->activeOwners($business) <= 1) {
            return back()->with('error', 'A business needs at least one active owner.');
        }

        $member->businesses()->detach($business->id);
        \App\Models\PosCartReservation::releaseForUser($member->id, $business->id);
        app(\App\Services\Audit\AuditLogger::class)->record('member_removed', $member, [], "{$member->name} removed from {$business->name}");

        return back()->with('success', "{$member->name} removed from the team.");
    }

    private function actorIsOwner(Request $request, $business): bool
    {
        return $request->user()->hasRole('super-admin') || (bool) $this->permissions->roleFor($request->user(), $business->id)?->isOwner();
    }

    /** Keep the legacy pivot role in step (the old Blade screens still read it). */
    private function pivotRole(BusinessRole $role): string
    {
        return match ($role->system_key) {
            'owner' => 'owner',
            'sales-person' => 'sales-person',
            default => 'staff',
        };
    }

    private function activeOwners(Business $business): int
    {
        $ownerRoleId = BusinessRole::query()->where('business_id', $business->id)->where('system_key', 'owner')->value('id');

        return DB::table('business_user')->where('business_id', $business->id)->where('status', 'active')
            ->where(fn ($w) => $w->where('business_role_id', $ownerRoleId)->orWhere(fn ($x) => $x->whereNull('business_role_id')->where('role', 'owner')))
            ->count();
    }

    private function assertMember(Business $business, User $member): void
    {
        abort_unless($member->businesses()->whereKey($business->id)->exists(), 404);
    }

    private function authorizeManage(Request $request): Business
    {
        abort_unless($request->user()->can('core.users.manage'), 403);

        return Business::findOrFail((int) (session('impersonate_business_id') ?? session('business_id')));
    }
}
