<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalogue\ProductSetupService;
use App\Services\Catalogue\SalePreferenceService;
use App\Services\Sales\SalePreferenceResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Sale preferences (Roadmap 5.4d): business-wide (Settings → Sale preferences) and per product (override or inherit). */
class SalePreferenceController extends Controller
{
    public function __construct(private readonly SalePreferenceService $prefs, private readonly ProductSetupService $setup)
    {
    }

    public function business(Request $request)
    {
        return Inertia::render('Settings/SalePreferences', [
            'settings' => $this->prefs->business((int) session('business_id')),
            'overrides' => fn () => [
                'price' => Product::query()->whereNotNull('sale_price_preference_id')->count(),
                'lot' => Product::query()->whereNotNull('lot_selection_mode')->count(),
                'pos' => Product::query()->where(fn ($w) => $w->whereNotNull('allow_price_change')->orWhereNotNull('min_price_percent'))->count(),
            ],
        ]);
    }

    public function saveBusiness(Request $request)
    {
        $this->prefs->saveBusiness((int) session('business_id'), $request->all());

        return back()->with('success', 'Sale preferences saved. They apply to the next sale.');
    }

    public function product(Request $request, $product)
    {
        $product = Product::query()->whereKey($product)->firstOrFail();

        return Inertia::render('Products/SalePreferences', [
            'product' => ['id' => $product->id, 'name' => $product->product_name, 'draft' => (bool) $product->is_draft],
            'overrides' => $this->prefs->product($product),
            'defaults' => $this->prefs->business((int) $product->business_id), // not "business": that name is the shared Inertia prop
            'effective' => (array) app(SalePreferenceResolver::class)->forProduct($product),
            'units' => collect($this->setup->chain($product))->map(fn ($l) => ['value' => $l['category_id'], 'label' => $l['name']])->values(),
        ]);
    }

    public function saveProduct(Request $request, $product)
    {
        $product = Product::query()->whereKey($product)->firstOrFail();
        $this->prefs->saveProduct($product, $request->all());

        return redirect()->route('products.show', $product->id)->with('success', "Sale preferences of “{$product->product_name}” saved.");
    }
}
