<?php

namespace App\Services\Reports;

use App\Models\BaseStockSalePrice;
use App\Models\Preference;
use App\Models\Product;
use App\Models\ProductParameter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Stock reports (Roadmap 5.9b). Stock always means the lots: remaining base units of batches that have not expired
 * (RP-3, RP-4: the purchased quantity and the cached counter are never used here).
 */
class StockReportService
{
    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    /** Batches with stock left, by expiry. $status: expired | 30 | 90 | later | null (all with an expiry). */
    public function expiryQuery(?string $status, string $q = ''): Builder
    {
        $today = today()->toDateString();

        return BaseStockSalePrice::query()->where('base_stock_sale_price.business_id', $this->businessId())
            ->where('remaining_base_stock', '>', 0)->whereNotNull('base_stock_sale_price.expiry_date')
            ->join('purchases', 'purchases.id', '=', 'base_stock_sale_price.purchase_id')
            ->join('products', 'products.id', '=', 'base_stock_sale_price.product_id')->whereNull('products.deleted_at')
            ->select('base_stock_sale_price.*', 'purchases.batch_no', 'products.product_name')
            ->selectRaw('remaining_base_stock * (case when purchases.base_quantity > 0 then (case when purchases.paid_extra_total_cost_price > 0 then purchases.paid_extra_total_cost_price else purchases.total_cost_price end) / purchases.base_quantity else 0 end) as value')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('products.product_name', 'like', "%{$q}%")->orWhere('purchases.batch_no', 'like', "%{$q}%")))
            ->when($status === 'expired', fn ($w) => $w->whereDate('base_stock_sale_price.expiry_date', '<=', $today))
            ->when($status === '30', fn ($w) => $w->whereDate('base_stock_sale_price.expiry_date', '>', $today)->whereDate('base_stock_sale_price.expiry_date', '<=', today()->addDays(30)))
            ->when($status === '90', fn ($w) => $w->whereDate('base_stock_sale_price.expiry_date', '>', today()->addDays(30))->whereDate('base_stock_sale_price.expiry_date', '<=', today()->addDays(90)))
            ->when($status === 'later', fn ($w) => $w->whereDate('base_stock_sale_price.expiry_date', '>', today()->addDays(90)));
    }

    public function expiryStats(): array
    {
        $out = [];
        foreach (['expired', '30', '90', 'later'] as $s) {
            $base = $this->expiryQuery($s)->reorder()->toBase();
            $base->columns = null; // totals only, without the list's own columns (only_full_group_by)
            $row = $base->selectRaw('count(*) as n, coalesce(sum(remaining_base_stock * (case when purchases.base_quantity > 0 then (case when purchases.paid_extra_total_cost_price > 0 then purchases.paid_extra_total_cost_price else purchases.total_cost_price end) / purchases.base_quantity else 0 end)),0) as v')->first();
            $out[$s] = ['count' => (int) $row->n, 'value' => round((float) $row->v, 2)];
        }

        return $out;
    }

    /**
     * Every product's alert rule and sellable stock, for products of the business that are ready to sell.
     * Rule: the product's own alert quantity in its alert unit, else the business-wide quantity in the largest unit.
     *
     * @return Collection<int, object{id:int, name:string, stock:float, threshold:?float, unit:?string, unit_size:float, own_rule:bool}>
     */
    public function alertRows(): Collection
    {
        $global = Preference::query()->where('business_id', $this->businessId())->where('slug', 'global_min_indicated_qty')->value('preference');
        $global = is_numeric($global) ? (float) $global : null;
        $today = today()->toDateString();

        $products = Product::query()->where('products.business_id', $this->businessId())->real()
            ->select('products.id', 'products.product_name', 'products.min_indicated_qty', 'products.min_qty_category_id')
            ->selectSub(BaseStockSalePrice::query()->whereColumn('base_stock_sale_price.product_id', 'products.id')->where('remaining_base_stock', '>', 0)
                ->where(fn ($w) => $w->whereNull('expiry_date')->orWhereDate('expiry_date', '>', $today))->selectRaw('coalesce(sum(remaining_base_stock),0)'), 'stock')
            ->orderBy('products.product_name')->get();

        // Packaging of all these products in one query; unit sizes worked out in memory.
        $params = ProductParameter::query()->whereIn('product_id', $products->pluck('id'))->get(['product_id', 'parent_category_id', 'child_category_id', 'quantity'])->groupBy('product_id');
        $unitNames = \App\Models\Category::withTrashed()->whereIn('id', $params->flatten()->pluck('child_category_id')->unique())->pluck('name', 'id');

        return $products->map(function ($p) use ($params, $global, $unitNames) {
            [$root, $sizes] = $this->sizes($params[$p->id] ?? collect());
            $own = $p->min_indicated_qty !== null && $p->min_qty_category_id;
            $unit = $own ? (int) $p->min_qty_category_id : ($global !== null ? $root : null);
            $qty = $own ? (float) $p->min_indicated_qty : $global;
            $size = $unit && isset($sizes[$unit]) ? $sizes[$unit] : null;

            return (object) [
                'id' => (int) $p->id, 'name' => $p->product_name, 'stock' => round((float) $p->stock, 4),
                'threshold' => $qty !== null && $size ? round($qty * $size, 4) : null, 'alert_qty' => $qty,
                'unit' => $unit ? ($unitNames[$unit] ?? null) : null, 'unit_size' => $size ?? 1.0, 'own_rule' => (bool) $own,
                'broken' => $qty !== null && $unit && ! $size, // rule unit no longer in the packaging
            ];
        });
    }

    /** [root unit, unit id => base units in one] from a product's parameter rows. */
    private function sizes(Collection $rows): array
    {
        $root = $rows->first(fn ($r) => (int) $r->parent_category_id === (int) $r->child_category_id);
        if (! $root) {
            return [null, []];
        }
        $chain = [(int) $root->child_category_id];
        $qty = [1.0];
        $current = (int) $root->child_category_id;
        for ($guard = 0; $guard < 10; $guard++) {
            $next = $rows->first(fn ($r) => (int) $r->parent_category_id === $current && (int) $r->child_category_id !== $current);
            if (! $next) {
                break;
            }
            $current = (int) $next->child_category_id;
            $chain[] = $current;
            $qty[] = (float) $next->quantity;
        }
        $sizes = [];
        $size = 1.0;
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $sizes[$chain[$i]] = $size;
            $size *= $qty[$i];
        }

        return [(int) $root->child_category_id, $sizes];
    }
}
