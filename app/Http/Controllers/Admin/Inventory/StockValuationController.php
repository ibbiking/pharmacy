<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Stock by warehouse and valuation (Inventory Pro, Roadmap step 7): one row per item and warehouse with the quantity
 * in stock and its cost (remaining × paid cost of each batch), paged on the server. Expired stock is shown apart.
 */
class StockValuationController extends Controller
{
    public function __invoke(Request $request)
    {
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $sort = in_array($request->input('sort'), ['name', 'qty', 'value'], true) ? $request->input('sort') : 'value';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $base = fn () => DB::table('base_stock_sale_price as l')
            ->join('purchases as p', 'p.id', '=', 'l.purchase_id')
            ->join('products as pr', 'pr.id', '=', 'l.product_id')
            ->where('l.business_id', $businessId)->whereNull('l.deleted_at')->where('l.remaining_base_stock', '>', 0)
            ->when($warehouseId, fn ($w) => $w->where('l.warehouse_id', $warehouseId));
        $cost = '(l.remaining_base_stock * (case when p.paid_extra_total_cost_price > 0 then p.paid_extra_total_cost_price else p.total_cost_price end) / nullif(p.base_quantity, 0))';
        $expired = '(l.expiry_date is not null and l.expiry_date <= curdate())';

        $rows = $base()
            ->leftJoin('warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->when($q !== '', fn ($w) => $w->where('pr.product_name', 'like', "%{$q}%"))
            ->groupBy('l.product_id', 'l.warehouse_id', 'pr.product_name', 'w.name')
            ->selectRaw("l.product_id, l.warehouse_id, pr.product_name as name, w.name as warehouse, sum(l.remaining_base_stock) as qty,
                sum(case when {$expired} then l.remaining_base_stock else 0 end) as expired_qty, sum({$cost}) as value, count(*) as batches")
            ->orderBy($sort === 'name' ? 'name' : $sort, $dir)
            ->paginate($perPage)->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->product_id.'-'.($r->warehouse_id ?? 0), 'product_id' => (int) $r->product_id, 'name' => $r->name, 'warehouse' => $r->warehouse ?? '—', 'qty' => (float) $r->qty,
                'expired_qty' => (float) $r->expired_qty, 'value' => round((float) $r->value, 2), 'batches' => (int) $r->batches,
                'unit' => app(\App\Services\Sales\PackagingService::class)->baseCategoryId((int) $r->product_id)
                    ? \App\Models\Category::withTrashed()->find(app(\App\Services\Sales\PackagingService::class)->baseCategoryId((int) $r->product_id))?->name : null,
            ]);

        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;

        return Inertia::render('Inventory/Valuation', [
            'rows' => $rows,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'warehouse_id' => $warehouseId],
            'warehouse' => $warehouse ? ['value' => $warehouse->id, 'label' => $warehouse->name] : null,
            'kpis' => fn () => [
                'value' => round((float) $base()->selectRaw("sum({$cost}) as v")->value('v'), 2),
                'expired_value' => round((float) $base()->whereRaw($expired)->selectRaw("sum({$cost}) as v")->value('v'), 2),
                'items' => (int) $base()->distinct()->count('l.product_id'),
                'warehouses' => Warehouse::count(),
            ],
            'currency' => currency_symbol(),
        ]);
    }
}
