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
