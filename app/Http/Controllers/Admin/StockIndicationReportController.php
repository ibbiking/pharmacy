<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Preference;
use App\Models\Category;
use Yajra\DataTables\Facades\DataTables;

class StockIndicationReportController extends Controller
{
    /** Stock alerts (Inertia, Roadmap 5.9b, RP-4): sellable stock at or below each product's alert level. */
    public function index(Request $request, \App\Services\Reports\StockReportService $stock)
    {
        $status = in_array($request->input('status'), ['out', 'ok'], true) ? $request->input('status') : null; // null = low (incl. out)
        $q = mb_strtolower(trim((string) $request->input('q', '')));
        $rows = $stock->alertRows()->filter(fn ($r) => $r->threshold !== null);
        $stats = ['low' => $rows->filter(fn ($r) => $r->stock <= $r->threshold)->count(), 'out' => $rows->filter(fn ($r) => $r->stock <= 0)->count(), 'ok' => $rows->filter(fn ($r) => $r->stock > $r->threshold)->count()];
        $rows = $rows->filter(fn ($r) => match ($status) { 'out' => $r->stock <= 0, 'ok' => $r->stock > $r->threshold, default => $r->stock <= $r->threshold })
            ->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r->name), $q)))
            ->sortBy(fn ($r) => $r->threshold > 0 ? $r->stock / $r->threshold : 0)->values();

        return \Inertia\Inertia::render('Reports/StockAlerts', [
            'rows' => $this->page($request, $rows->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'stock' => round($r->stock / $r->unit_size, 2), 'alert' => $r->alert_qty, 'unit' => $r->unit, 'own_rule' => $r->own_rule,
            ])),
            'filters' => ['q' => $request->input('q', ''), 'status' => $status, 'per_page' => 20, 'sort' => 'stock', 'dir' => 'asc'],
            'stats' => $stats,
        ]);
    }

    /** Products without an alert level (or whose alert unit left their packaging). */
    public function unaligned(Request $request, \App\Services\Reports\StockReportService $stock)
    {
        $q = mb_strtolower(trim((string) $request->input('q', '')));
        $rows = $stock->alertRows()->filter(fn ($r) => $r->threshold === null)
            ->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r->name), $q)))->values();

        return \Inertia\Inertia::render('Reports/StockGaps', [
            'rows' => $this->page($request, $rows->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'stock' => round($r->stock, 2), 'broken' => $r->broken])),
            'filters' => ['q' => $request->input('q', ''), 'per_page' => 20, 'sort' => 'name', 'dir' => 'asc'],
            'total' => $rows->count(),
        ]);
    }

    /** Page an already-computed list (20 per page): the browser never gets the whole list. */
    private function page(Request $request, $items)
    {
        $page = max(1, $request->integer('page', 1));

        return new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($page, 20)->values(), $items->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
