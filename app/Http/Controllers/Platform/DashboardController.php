<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Platform home: every business type at a glance, recent sign-ups and what platform admins did last. */
class DashboardController extends Controller
{
    public function __invoke(ModuleManager $modules)
    {
        $since = now()->subDays(30);
        $types = Business::query()->selectRaw('business_type, count(*) as n')->groupBy('business_type')->pluck('n', 'business_type');
        $sales = DB::table('invoices')->whereNull('deleted_at');

        return Inertia::render('Platform/Dashboard', [
            'stats' => [
                'businesses' => Business::query()->count(),
                'new_businesses' => Business::query()->where('created_at', '>=', $since)->count(),
                'people' => DB::table('users')->whereNull('deleted_at')->count(),
                'sales_today' => (clone $sales)->whereDate('created_at', today())->count(),
                'sales_30' => (clone $sales)->where('created_at', '>=', $since)->count(),
            ],
            'types' => collect(\App\Support\BusinessTypes::keys())->map(fn ($t) => [
                'key' => $t, 'name' => $modules->typeName($t), 'count' => (int) ($types[$t] ?? 0),
            ])->values(),
            'recent' => Business::query()->latest('id')->limit(8)->get(['id', 'name', 'business_type', 'created_at'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'type' => $modules->typeName($b->business_type), 'created_at' => $b->created_at?->format('d M Y')]),
            'activity' => DB::table('platform_audit_logs')->leftJoin('platform_admins', 'platform_admins.id', '=', 'platform_audit_logs.platform_admin_id')
                ->leftJoin('businesses', 'businesses.id', '=', 'platform_audit_logs.business_id')
                ->orderByDesc('platform_audit_logs.id')->limit(10)
                ->get(['platform_audit_logs.action', 'platform_audit_logs.created_at', 'platform_admins.name as admin', 'businesses.name as business'])
                ->map(fn ($a) => ['action' => $a->action, 'admin' => $a->admin, 'business' => $a->business, 'at' => \Illuminate\Support\Carbon::parse($a->created_at)->format('d M Y H:i')]),
        ]);
    }
}
