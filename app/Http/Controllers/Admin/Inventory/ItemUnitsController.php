<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\BaseStockSalePrice;
use App\Models\Category;
use App\Models\Product;
use App\Services\Sales\PackagingService;
use App\Services\Sales\SalePreferenceResolver;
use App\Services\Sales\SalePriceCalculator;
use Illuminate\Http\Request;

/**
 * Units of an item for line forms (transfers, adjustments, B2B documents): each unit with its size, sale price and
 * last cost, and the stock per warehouse. Read-only; every amount is computed again on save.
 */
class ItemUnitsController extends Controller
{
    public function __invoke(Request $request, int $product, PackagingService $packaging)
    {
        abort_unless(collect(['inventory.view', 'b2b_sales.manage', 'b2b_purchase.manage', 'stock.purchases.manage'])->contains(fn ($p) => $request->user()->can($p)), 403);
        $product = Product::query()->real()->findOrFail($product);
        $units = $packaging->units($product->id);
        $names = Category::withTrashed()->whereIn('id', array_keys($units))->pluck('name', 'id');
        $settings = app(SalePreferenceResolver::class)->forProduct($product);
        $prices = app(SalePriceCalculator::class);
        $lastCost = (float) (\App\Models\Purchase::query()->where('product_id', $product->id)->latest('id')->value('base_unit_purchase_price') ?? 0);
        $taxRate = (float) \App\Models\SaleTax::query()->where('product_id', $product->id)->latest('id')->value('tax_rate');

        $stock = BaseStockSalePrice::query()->where('product_id', $product->id)->where('remaining_base_stock', '>', 0)
            ->selectRaw('warehouse_id, sum(remaining_base_stock) as qty')->groupBy('warehouse_id')->pluck('qty', 'warehouse_id');
        $warehouseNames = \App\Models\Inventory\Warehouse::withTrashed()->whereIn('id', $stock->keys()->filter())->pluck('name', 'id');

        return response()->json([
            'id' => $product->id,
            'name' => $product->product_name,
            'base_unit' => $names[$packaging->baseCategoryId($product->id)] ?? null,
            'units' => collect($units)->map(function ($per, $id) use ($names, $prices, $product, $settings, $lastCost) {
                try {
                    $sale = $prices->unitPrice($product->id, (int) $id, $settings);
                } catch (\Throwable) {
                    $sale = 0.0;
                }

                return ['value' => (int) $id, 'label' => $names[$id] ?? '?', 'base_units' => (float) $per, 'sale_price' => round((float) $sale, 2), 'cost' => round($lastCost * (float) $per, 2)];
            })->values(),
            'tax_rate' => $taxRate,
            'stock' => $stock->map(fn ($qty, $wid) => ['warehouse_id' => $wid ? (int) $wid : null, 'warehouse' => $wid ? ($warehouseNames[$wid] ?? '—') : 'All', 'base_qty' => (float) $qty])->values(),
            'total_base' => (float) $stock->sum(),
        ]);
    }
}
