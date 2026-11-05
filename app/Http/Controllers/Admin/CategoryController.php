<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Packaging units / units of measure (Box, Strip, Tablet…). The per-product chain (Box → Strip → Tablet) lives in
 * product_categories; this list manages the units themselves (Inertia, Roadmap step 5).
 */
class CategoryController extends MasterDataController
{
    protected function definition(): array
    {
        return [
            'model' => Category::class,
            'route' => 'categories',
            'wording' => ['category', 'categories'],
            'icon' => 'boxes',
            'accent' => 'var(--chart-1)',
            'fk' => 'min_qty_category_id',
            'max' => 100,
            'help' => 'master-data',
            'examples' => 'e.g. Box, Strip, Tablet, Bottle',
        ];
    }

    /** A unit is in use when a product's packaging chain, unit/price chain or minimum-quantity rule uses it. */
    protected function usageSubquery(string $idColumn): Builder
    {
        return Product::query()->selectRaw('count(*)')->where(fn ($w) => $w
            ->whereColumn('products.min_qty_category_id', $idColumn)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('product_categories as pc')
                ->whereColumn('pc.product_id', 'products.id')->whereNull('pc.deleted_at')
                ->where(fn ($x) => $x->whereColumn('pc.parent_category_id', $idColumn)->orWhereColumn('pc.child_category_id', $idColumn)))
            // The unit chain used for pricing and selling.
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('product_parameters as pp')
                ->whereColumn('pp.product_id', 'products.id')->whereNull('pp.deleted_at')
                ->where(fn ($x) => $x->whereColumn('pp.category_id', $idColumn)->orWhereColumn('pp.parent_category_id', $idColumn)->orWhereColumn('pp.child_category_id', $idColumn))));
    }
}
