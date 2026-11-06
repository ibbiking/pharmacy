<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\DashboardService;
use App\Services\Reports\SalesReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The owner's home screen (Inertia, Roadmap 5.10). Each panel is a deferred prop: the page opens at once and fills in.
 * Sales persons go straight to the POS.
 */
class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        if ($request->user()->isSalesPersonIn($businessId)) {
            return redirect()->route('pos.index');
        }

        $preset = in_array($request->input('range'), ['today', '7d', '30d', 'month', 'last_month', 'custom'], true) ? $request->input('range') : '30d';
        [$from, $to] = SalesReportService::range($preset, $request->input('from'), $request->input('to'));
        if ($from > $to) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return Inertia::render('Dashboard', [
            'filters' => ['range' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => currency_symbol(),
            'kpis' => Inertia::defer(fn () => $dashboard->kpis($from, $to), 'kpis'),
            'charts' => Inertia::defer(fn () => $dashboard->charts($from, $to), 'charts'),
            'panels' => Inertia::defer(fn () => $dashboard->panels(), 'panels'),
            'suggestions' => Inertia::defer(fn () => $dashboard->suggestions(), 'suggestions'),
        ]);
    }
}
