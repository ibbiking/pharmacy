<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemReturn;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class ReportController extends Controller
{
    /** Date range from the shared filter (preset or custom), plus the props every report page gets. */
    private function rangeProps(Request $request): array
    {
        $preset = in_array($request->input('range'), ['today', '7d', '30d', 'month', 'last_month', 'custom'], true) ? $request->input('range') : 'month';
        [$from, $to] = \App\Services\Reports\SalesReportService::range($preset, $request->input('from'), $request->input('to'));
        if ($from > $to) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, ['range' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString()]];
    }

    /** Sales report (Inertia, Roadmap 5.9a). */
    public function sales(Request $request, \App\Services\Reports\SalesReportService $reports)
    {
        [$from, $to, $filters] = $this->rangeProps($request);
        $best = $reports->bestSellers($from, $to);
        $best->setCollection($best->getCollection()->map(fn ($r) => ['product_id' => (int) $r->product_id, 'name' => $r->name, 'revenue' => round((float) $r->revenue, 2), 'base_qty' => (float) $r->base_qty, 'lines' => (int) $r->line_count]));

        return \Inertia\Inertia::render('Reports/Sales', ['filters' => $filters, 'report' => $reports->sales($from, $to), 'best' => $best, 'currency' => currency_symbol()]);
    }

    /** Profit & loss (Inertia, Roadmap 5.9a): actual cost from the batches sold. */
    public function profitLoss(Request $request, \App\Services\Reports\SalesReportService $reports)
    {
        [$from, $to, $filters] = $this->rangeProps($request);
        $report = $reports->profit($from, $to);
        $page = max(1, $request->integer('page', 1));
        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $report['products']->forPage($page, 20)->values(), $report['products']->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        unset($report['products']);

        return \Inertia\Inertia::render('Reports/Profit', ['filters' => $filters, 'report' => $report, 'products' => $products, 'currency' => currency_symbol()]);
    }

    /** Expiry (Inertia, Roadmap 5.9b): batches with stock left (RP-3), value at cost. */
    public function expiry(Request $request, \App\Services\Reports\StockReportService $stock)
    {
        $status = in_array($request->input('status'), ['expired', '30', '90', 'later'], true) ? $request->input('status') : null;
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $page = $stock->expiryQuery($status, $q)->orderBy('base_stock_sale_price.expiry_date')->orderBy('base_stock_sale_price.id')->paginate($perPage)->withQueryString();
        $units = \App\Models\Category::withTrashed()->whereIn('id', $page->getCollection()->pluck('base_category_id')->filter())->pluck('name', 'id');
        $page->setCollection($page->getCollection()->map(fn ($l) => [
            'id' => $l->id, 'product_id' => $l->product_id, 'product' => $l->product_name, 'batch' => $l->batch_no, 'purchase_id' => $l->purchase_id,
            'remaining' => (float) $l->remaining_base_stock, 'unit' => $units[$l->base_category_id] ?? null, 'value' => round((float) $l->value, 2),
            'expiry' => Carbon::parse($l->expiry_date)->format('d M Y'), 'days' => (int) today()->diffInDays(Carbon::parse($l->expiry_date), false),
        ]));

        return \Inertia\Inertia::render('Reports/Expiry', [
            'rows' => $page, 'filters' => ['q' => $q, 'status' => $status, 'per_page' => $perPage, 'sort' => 'expiry_date', 'dir' => 'asc'],
            'stats' => fn () => $stock->expiryStats(), 'currency' => currency_symbol(),
        ]);
    }
}
