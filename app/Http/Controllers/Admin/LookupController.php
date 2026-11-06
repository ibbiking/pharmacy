<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * One paginated, searchable lookup endpoint for every dropdown in the new UI:
 *   GET /admin/lookup/{entity}?q=para&page=2
 *   → { data: [{id, label, meta?}], next_page: 3|null, total: 123 }
 *
 * Global rule: dropdowns never load a full table. Pages are 20 rows (per_page 10–50 allowed).
 * Business scoping comes from each model's BusinessScope (BelongsToBusiness).
 */
class LookupController extends Controller
{
    /** entity => [model class, label column, extra search columns, optional meta columns, optional model scope] */
    private const ENTITIES = [
        'taxes' => [\App\Models\Tax::class, 'name', [], ['rate']],
        'suppliers' => [\App\Models\Supplier::class, 'name', ['phone', 'email'], ['phone']],
        'categories' => [\App\Models\Category::class, 'name', [], []],
        'companies' => [\App\Models\Company::class, 'name', [], []],
        'product-types' => [\App\Models\ProductType::class, 'name', [], []],
        'strengths' => [\App\Models\Strength::class, 'name', [], []],
        'formulas' => [\App\Models\Farmula::class, 'name', [], []],
        'products' => [\App\Models\Product::class, 'product_name', ['barcode'], ['barcode']],
        // Products that can be bought or sold (packaging set up): purchase form, POS.
        'stock-products' => [\App\Models\Product::class, 'product_name', ['barcode'], ['barcode'], 'real'],
        // Shared generic catalogue master data (approved + this business's own suggestions), Roadmap 5.6b.
        'generic-companies' => [\App\Models\GenericCompany::class, 'name', [], [], 'visibleInCatalogue'],
        'generic-types' => [\App\Models\GenericProductType::class, 'name', [], [], 'visibleInCatalogue'],
        'generic-strengths' => [\App\Models\GenericStrength::class, 'name', [], [], 'visibleInCatalogue'],
        'generic-formulas' => [\App\Models\GenericFarmula::class, 'name', [], [], 'visibleInCatalogue'],
        'generic-units' => [\App\Models\GenericCategory::class, 'name', [], [], 'visibleInCatalogue'],
    ];

    public function __invoke(Request $request, string $entity)
    {
        abort_unless(isset(self::ENTITIES[$entity]), 404);

        [$model, $labelColumn, $searchColumns, $metaColumns] = self::ENTITIES[$entity];
        $scope = self::ENTITIES[$entity][4] ?? null;
        $perPage = min(50, max(10, (int) $request->integer('per_page', 20)));
        $term = trim((string) $request->input('q', ''));

        $query = $model::query()
            ->when($scope, fn ($q) => $q->{$scope}())
            ->select(array_merge(['id', $labelColumn], $metaColumns))
            ->when($term !== '', function ($q) use ($term, $labelColumn, $searchColumns) {
                $q->where(function ($w) use ($term, $labelColumn, $searchColumns) {
                    $w->where($labelColumn, 'like', "%{$term}%");
                    foreach ($searchColumns as $column) {
                        $w->orWhere($column, 'like', "%{$term}%");
                    }
                });
            })
            // Exact / prefix matches first, then alphabetical.
            ->when($term !== '', fn ($q) => $q->orderByRaw("CASE WHEN {$labelColumn} = ? THEN 0 WHEN {$labelColumn} LIKE ? THEN 1 ELSE 2 END", [$term, "{$term}%"]))
            ->orderBy($labelColumn)
            ->orderBy('id');

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($row) => array_filter([
                'id' => $row->id,
                'label' => (string) $row->{$labelColumn},
                'meta' => $metaColumns ? collect($metaColumns)->mapWithKeys(fn ($c) => [$c => $row->{$c}])->all() : null,
            ], fn ($v) => $v !== null))->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }
}
