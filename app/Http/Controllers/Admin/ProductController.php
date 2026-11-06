<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Category;
use App\Models\Company;
use App\Models\Farmula;
use App\Models\ProductParameter;
use App\Models\ProductStock;
use App\Models\ProductPreference;
use App\Models\BaseStockSalePrice;
use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;

class ProductController extends Controller
{
    // Add / edit / archive / restore moved to Catalogue\ProductCatalogController (Inertia, Roadmap 5.4b).

    public function stockSummary($id)
    {
        $product = Product::findOrFail($id);
        $summary = $this->getStockSummary($id);

        return response()->json([
            'product_name' => $product->product_name,
            'summary'      => $summary
        ]);
    }

    // get current stock of a product
    
    public function getCategoryStock($productId, $targetCategoryId, $stock = null)
    {
        if (is_null($stock)) {
            $stock = \App\Models\ProductStock::where('product_id', $productId)->sum('current_stock');
        }
        if (!$stock || $stock <= 0) return 0;
        
        $params = \App\Models\ProductParameter::where('product_id', $productId)->get();
        if ($params->isEmpty()) return 0;
        
        $baseSelf = $params->first(function ($p) {
            return $p->parent_category_id == $p->child_category_id;
        });
        
        if (!$baseSelf) return 0;
        $baseCategoryId = $baseSelf->child_category_id;
        
        $summary = [];
        $currentQty = $stock;
        $currentCat = $baseCategoryId;
        $summary[$currentCat] = $currentQty;

        while (true) {
            $parentId   = null;
            $multiplier = null;

            foreach ($params as $p) {
                if ($p->child_category_id == $currentCat && $p->parent_category_id != $p->child_category_id) {
                    $parentId   = $p->parent_category_id;
                    $multiplier = $p->quantity;
                    break;
                }
            }

            if (!$parentId) break;

            $parentQty = $currentQty / $multiplier;
            $summary[$parentId] = $parentQty;

            $currentCat = $parentId;
            $currentQty = $parentQty;
        }
        
        return $summary[$targetCategoryId] ?? 0;
    }

    public function getStockSummary($productId)
    {
        // Sourced from BaseStockSalePrice.remaining_base_stock — the same
        // running total that's actually decremented on every sale — not
        // StockPrices.base_stock, which is stamped once at purchase time and
        // never decremented, so it only ever reflects lifetime-purchased
        // minus expired, not what's truly left right now.
        $today = \Carbon\Carbon::today()->toDateString();

        $availableBase = \App\Models\BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', $today)
            ->sum('remaining_base_stock');

        $expiredBase = \App\Models\BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '<', $today)
            ->sum('remaining_base_stock');

        // Net out stock already sitting in ANY sales-person's in-progress
        // cart — this is a display total across everyone, not per-user
        // netting, so no exclusion is passed.
        $reservedBuckets = \App\Models\PosCartReservation::reservedBucketsForProduct($productId, null);
        $reservedTotal = array_sum($reservedBuckets['per_batch']) + array_sum($reservedBuckets['per_price']);
        $availableBase = max(0, $availableBase - $reservedTotal);

        $params = ProductParameter::where('product_id', $productId)->get();

        $availableSummary = $this->calculateStockSummaryInternal($availableBase, $params);
        $expiredSummary   = $this->calculateStockSummaryInternal($expiredBase, $params);

        return [
            'available' => $availableSummary,
            'expired'   => $expiredSummary,
        ];
    }

    /**
     * True net availability + who else is holding stock, for the POS
     * hover-detail panel. Reuses getStockSummary() (already netted against
     * every active cart reservation) for the per-category quantities, and
     * adds a display-only "held by" breakdown on top.
     */
    public function posAvailability($id)
    {
        $product = Product::findOrFail($id);
        $summary = $this->getStockSummary($id);
        $heldBy = \App\Models\PosCartReservation::heldByOthersForProduct($id, auth()->id());

        return response()->json([
            'product_name' => $product->product_name,
            'summary' => $summary,
            'held_by' => $heldBy->map(function ($row) {
                return [
                    'name' => $row->user_name,
                    'qty' => (float) ($row->total_quantity ?? $row->total_base_qty),
                ];
            })->values(),
        ]);
    }

    private function calculateStockSummaryInternal($stock, $params)
    {
        if (!$stock || $stock <= 0) {
            return [];
        }

        $map = [];
        foreach ($params as $p) {
            // skip self-row
            if ($p->parent_category_id == $p->child_category_id) {
                continue;
            }
            $map[$p->parent_category_id][$p->child_category_id] = $p->quantity;
        }

        // Find base category (deepest child)
        $baseCategoryId = null;
        foreach ($map as $parent => $children) {
            foreach ($children as $child => $qty) {
                if (!isset($map[$child])) {
                    $baseCategoryId = $child;
                }
            }
        }

        // Single-packaging products may only have self-row (parent == child).
        if (!$baseCategoryId) {
            $baseSelf = $params->first(function ($p) {
                return $p->parent_category_id == $p->child_category_id;
            });
            if ($baseSelf) {
                $baseCategoryId = $baseSelf->category_id ?: $baseSelf->child_category_id;
            }
        }

        if (!$baseCategoryId) {
            return [];
        }

        $summary = [];
        $currentQty = $stock;
        $currentCat = $baseCategoryId;
        $summary[$currentCat] = $currentQty;

        // Walk upward
        while (true) {
            $parentId   = null;
            $multiplier = null;

            foreach ($params as $p) {
                if ($p->child_category_id == $currentCat && $p->parent_category_id != $p->child_category_id) {
                    $parentId   = $p->parent_category_id;
                    $multiplier = $p->quantity;
                    break;
                }
            }

            if (!$parentId) break;

            $parentQty = $currentQty / $multiplier;
            $summary[$parentId] = $parentQty;

            $currentCat = $parentId;
            $currentQty = $parentQty;
        }

        // Add base self-row category explicitly
        $baseSelf = $params->first(function ($p) {
            return $p->parent_category_id == $p->child_category_id;
        });
        if ($baseSelf) {
            $summary[$baseSelf->category_id] = $summary[$baseSelf->category_id] ?? $stock;
        }

        // Build response
        $result = [];
        foreach ($summary as $catId => $qty) {
            if (!$catId) {
                continue;
            }
            $name = Category::find($catId)->name ?? 'Unknown';
            $result[] = [
                'category' => $name,
                'quantity' => number_format($qty, 2, '.', ''),
            ];
        }

        return $result;
    }

    public function getProductCategories($productId)
    {
        $categoryIds = ProductParameter::where('product_id', $productId)
            ->get()
            ->flatMap(function ($param) {
                return [
                    $param->category_id,
                    $param->parent_category_id == $param->child_category_id ? null : $param->parent_category_id,
                    $param->child_category_id,
                ];
            })
            ->filter() // remove nulls
            ->unique()
            ->values();

        $categories = Category::whereIn('id', $categoryIds)->get(['id', 'name']);

        return response()->json($categories);
    }

    // Sale preference screens moved to Catalogue\SalePreferenceController (Inertia, Roadmap 5.4d).

    /**
     * Effective sale-price preference for a product (product → business-wide → default).
     * Thin wrapper over SalePreferenceResolver kept for existing callers; 'settings' carries the full
     * SaleSettings (lot mode, price-change and discount rules).
     */
    public function getSalePricePreference($productId)
    {
        $settings = app(\App\Services\Sales\SalePreferenceResolver::class)->forProduct((int) $productId);

        $labels = [
            'static-price' => 'Static Sale Price',
            'stock-wise-price' => 'Stock wise Sale Price',
            'previous-inventory-price' => 'Last/previous inventory Sale Price',
        ];

        return [
            'type' => ['product' => 'product', 'business' => 'global', 'default' => 'default'][$settings->priceModeSource],
            'preference' => (object) ['slug' => $settings->priceMode, 'preference' => $labels[$settings->priceMode] ?? 'Static Sale Price'],
            'including_tax' => $settings->includeTax,
            'settings' => $settings,
        ];
    }
    /**
     * System sale price of one unit (SalePriceCalculator). $preferenceInfo is the array returned by
     * getSalePricePreference(); a caller-supplied slug/including_tax still wins for compatibility.
     */
    public function calculateSalePrice($productId, $selectedCategoryId, $preferenceInfo)
    {
        $settings = $preferenceInfo['settings'] ?? null;
        if (!$settings instanceof \App\Services\Sales\SaleSettings) {
            $settings = app(\App\Services\Sales\SalePreferenceResolver::class)->forProduct((int) $productId);
        }

        $slug = $preferenceInfo['preference']->slug ?? $settings->priceMode;
        $includeTax = (bool) ($preferenceInfo['including_tax'] ?? $settings->includeTax);
        if ($slug !== $settings->priceMode || $includeTax !== $settings->includeTax) {
            $settings = new \App\Services\Sales\SaleSettings(
                in_array($slug, \App\Services\Sales\SaleSettings::PRICE_MODES, true) ? $slug : 'static-price',
                $includeTax, $settings->lotMode, $settings->allowPriceChange, $settings->minPricePercent,
                $settings->productMaxDiscountPercent, $settings->productMaxDiscountAmount,
                $settings->maxLineDiscountPercent, $settings->maxInvoiceDiscountPercent, $settings->priceModeSource
            );
        }

        return app(\App\Services\Sales\SalePriceCalculator::class)
            ->unitPrice((int) $productId, $selectedCategoryId ? (int) $selectedCategoryId : null, $settings);
    }


    /**
     * Calculate price for a category by multiplying base price with quantities from product_parameters
     */
    public function calculateCategoryPrice($productId, $selectedCategoryId, $baseCategoryId, $basePrice)
    {
        // If selected and base are same, return base price directly
        if ($selectedCategoryId == $baseCategoryId) {
            return $basePrice;
        }

        // Load all parameters for this product
        $parameters = ProductParameter::where('product_id', $productId)->get();

        // Build child → parent relationships and quantity mapping
        $childToParent = [];
        $childQuantities = [];

        foreach ($parameters as $param) {
            if ($param->parent_category_id != $param->child_category_id) {
                $childToParent[$param->child_category_id] = $param->parent_category_id;
                $childQuantities[$param->child_category_id] = $param->quantity;
            }
        }

        // Start from base category, move UP until we reach selected category
        $current = $baseCategoryId;
        $multiplier = 1;

        while (isset($childToParent[$current])) {
            $parent = $childToParent[$current];
            $multiplier *= $childQuantities[$current];

            // If we've reached the selected category, return calculated price
            if ($parent == $selectedCategoryId) {
                return $basePrice * $multiplier;
            }

            $current = $parent;
        }

        // If no path found (not connected in hierarchy)
        return 0;
    }

    /**
     * Check if product has stock available
     */
    public function hasStockAvailable($productId)
    {
        $preferenceInfo = $this->getSalePricePreference($productId);
        $preferenceSlug = $preferenceInfo['preference']->slug ?? 'static-price';

        // ✅ FOR FIFO (stock-wise-price)
        if ($preferenceSlug === 'stock-wise-price') {
            return BaseStockSalePrice::where('product_id', $productId)
                ->where('remaining_base_stock', '>', 0)
                ->where('expiry_date', '>=', now())
                ->exists();
        }

        // ✅ FOR static-price & previous-inventory-price
        return ProductStock::where('product_id', $productId)
            ->where('current_stock', '>', 0)
            ->exists();
    }

    public function getMainCategoryId($productId)
    {
        $parameters = ProductParameter::where('product_id', $productId)->get();

        // Exclude self-references to find valid parent linkages
        $validLinks = $parameters->filter(function($p) {
            return $p->parent_category_id != $p->child_category_id;
        });

        // Get all parent categories and child categories from true linkages
        $parentCategories = $validLinks->pluck('parent_category_id')->unique()->filter()->toArray();
        $childCategories = $validLinks->pluck('child_category_id')->unique()->filter()->toArray();

        // The main category is a category that acts as a parent but NEVER as a child
        $mainCategories = array_diff($parentCategories, $childCategories);

        if (!empty($mainCategories)) {
            return reset($mainCategories);
        }

        // If no true linkages exist (e.g., single self-mapped packaging)
        $selfLink = $parameters->first();
        if ($selfLink) {
            return $selfLink->parent_category_id ?: $selfLink->category_id;
        }

        return null;
    }

    public function getBaseCategoryId($productId)
    {
        $parameters = ProductParameter::where('product_id', $productId)->get();

        // Exclude self-references to find valid parent linkages
        $validLinks = $parameters->filter(function($p) {
            return $p->parent_category_id != $p->child_category_id;
        });

        // Find all child categories that appear as parents in actual linkages
        $parentCategories = $validLinks->pluck('parent_category_id')->unique()->filter()->toArray();

        // Find child categories that are NOT functional parents (these are the base categories)
        $baseCategories = $parameters->pluck('child_category_id')->unique()->filter()->toArray();
        $baseCategories = array_diff($baseCategories, $parentCategories);

        return !empty($baseCategories) ? reset($baseCategories) : null;
    }

    /**
     * Autocomplete product names for Select2
     */
    public function autocompleteName(Request $request)
    {
        $query = $request->get('term');

        $products = Product::where('product_name', 'like', "%{$query}%")
            ->select('product_name')
            ->distinct()
            ->orderBy('product_name', 'asc')
            ->simplePaginate(10);
            
        $formattedProducts = [];
        foreach ($products as $product) {
            $formattedProducts[] = [
                'id' => $product->product_name, 
                'text' => $product->product_name
            ];
        }
        
        return response()->json([
            'results' => $formattedProducts,
            'pagination' => [
                'more' => $products->hasMorePages()
            ]
        ]);
    }

    /**
     * Modified search method with preference-based pricing
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        if (empty($query)) {
            return response()->json([]);
        }

        $farmulaIds = \App\Models\Farmula::where('name', 'like', "%{$query}%")->pluck('id')->toArray();

        $products = Product::where('is_draft', false)
            ->where(function($q) use ($query, $farmulaIds) {
                $q->where('barcode', $query)
                  ->orWhere('product_name', 'like', "%{$query}%");
                
                if (!empty($farmulaIds)) {
                    foreach ($farmulaIds as $id) {
                        $q->orWhereRaw('FIND_IN_SET(?, farmula_id)', [$id]);
                    }
                }
            })
            ->with([
                'parameters.childCategory:id,name',
                'strength',
                'type:id,name'
            ])
            ->orderByRaw("CASE WHEN barcode = ? THEN 0 ELSE 1 END", [$query])
            ->limit(20)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([]);
        }

        // Get current cart for stock calculation
        if ($request->has('current_cart')) {
            $cartInput = $request->get('current_cart');
            $cart = is_string($cartInput) ? (json_decode($cartInput, true) ?: []) : (is_array($cartInput) ? $cartInput : []);
        } else {
            $cart = $request->session()->get('pos_cart', []);
        }

        if (!is_array($cart)) {
            $cart = [];
        }

        $responseData = $products->map(function ($product) use ($cart) {
            // Get cart items for this product
            $cartItemsForProduct = array_filter($cart, function ($item) use ($product) {
                return is_array($item) && isset($item['product_id']) && $item['product_id'] == $product->id;
            });

            // Calculate total reserved base quantity
            $totalReservedBaseQty = array_sum(array_column($cartItemsForProduct, 'base_qty'));

            // Check if any stock is available after cart reservation
            $totalAvailableBase = BaseStockSalePrice::where('product_id', $product->id)
                ->where('remaining_base_stock', '>', 0)
                ->where('expiry_date', '>=', now())
                ->sum('remaining_base_stock');

            $actuallyAvailable = max(0, $totalAvailableBase - $totalReservedBaseQty);

            // Construct Categories Early So 'Out of stock' block still supplies them to UI!
            $preferenceInfo = $this->getSalePricePreference($product->id);

            $categories = $product->parameters->map(function ($param) use ($product, $preferenceInfo) {
                if (!$param->childCategory) return null;

                $categoryPrice = $this->calculateSalePrice($product->id, $param->child_category_id, $preferenceInfo);

                return [
                    'id' => $param->child_category_id,
                    'name' => $param->childCategory->name,
                    'is_base' => $param->parent_category_id == $param->child_category_id,
                    'price' => $categoryPrice,
                    'base_quantity' => $param->base_quantity,
                ];
            })->filter()->sortByDesc('base_quantity')->values();

            if ($categories->isEmpty()) {
                $baseCatId = $this->getBaseCategoryId($product->id);
                if ($baseCatId) {
                    $categoryObj = \App\Models\Category::find($baseCatId);
                    if ($categoryObj) {
                        $categoryPrice = $this->calculateSalePrice($product->id, $baseCatId, $preferenceInfo);
                        $categories->push([
                            'id' => $baseCatId,
                            'name' => $categoryObj->name,
                            'is_base' => true,
                            'price' => $categoryPrice,
                            'base_quantity' => 1,
                        ]);
                    }
                }
            }

            if ($actuallyAvailable <= 0) {
                $formulaNames = '';
                if ($product->farmula_id) {
                    $ids = explode(',', $product->farmula_id);
                    $names = \App\Models\Farmula::whereIn('id', $ids)->pluck('name', 'id');
                    $orderedNames = [];
                    foreach($ids as $id) {
                        if(isset($names[$id])) {
                            $orderedNames[] = $names[$id];
                        }
                    }
                    $formulaNames = implode(', ', $orderedNames);
                }

                $strengthNames = '';
                if ($product->strength_id) {
                    $sIds = explode(',', $product->strength_id);
                    $sNames = \App\Models\Strength::whereIn('id', $sIds)->pluck('name', 'id');
                    $orderedSNames = [];
                    foreach($sIds as $id) {
                        if(isset($sNames[$id])) {
                            $orderedSNames[] = $sNames[$id];
                        }
                    }
                    $strengthNames = implode(', ', $orderedSNames);
                }

                $fallbackCategory = $categories->last();
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'product_type' => $product->type ? $product->type->name : '',
                    'strength' => $strengthNames ? ['name' => $strengthNames] : null,
                    'farmula' => $formulaNames,
                    'price' => 0,
                    'out_of_stock' => true,
                    'message' => 'No stock available (considering cart items)',
                    'default_category_id' => is_array($fallbackCategory) ? ($fallbackCategory['id'] ?? null) : null,
                    'categories' => $categories,
                    'discount' => $product->discount ?? 0,
                    'discount_percent' => $product->discount_percent ?? 0,
                    'lock_max_discount' => (bool) $product->lock_max_discount,
                ];
            }

            // POS Smart Category Allocation (Bypassed due to base_quantity null comparison)
            $smartCategoryId = null;
            foreach ($categories as $cat) {
                 if ($cat['base_quantity'] > 0 && ($actuallyAvailable / $cat['base_quantity']) >= 1) {
                     $smartCategoryId = $cat['id'];
                     break;
                 }
            }

            if (!$smartCategoryId) {
                 $fallbackCategory = $categories->last();
                 $smartCategoryId = is_array($fallbackCategory) ? ($fallbackCategory['id'] ?? null) : null;
            }

            $defaultCategoryId = $smartCategoryId;

            $defaultPrice = $defaultCategoryId
                ? $categories->where('id', $defaultCategoryId)->first()['price'] ?? 0
                : 0;

            $formulaNames = '';
            if ($product->farmula_id) {
                $ids = explode(',', $product->farmula_id);
                $names = \App\Models\Farmula::whereIn('id', $ids)->pluck('name', 'id');
                $orderedNames = [];
                foreach($ids as $id) {
                    if(isset($names[$id])) {
                        $orderedNames[] = $names[$id];
                    }
                }
                $formulaNames = implode(', ', $orderedNames);
            }

            $strengthNames = '';
            if ($product->strength_id) {
                $sIds = explode(',', $product->strength_id);
                $sNames = \App\Models\Strength::whereIn('id', $sIds)->pluck('name', 'id');
                $orderedSNames = [];
                foreach($sIds as $id) {
                    if(isset($sNames[$id])) {
                        $orderedSNames[] = $sNames[$id];
                    }
                }
                $strengthNames = implode(', ', $orderedSNames);
            }

            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'product_type' => $product->type ? $product->type->name : '',
                'strength' => $strengthNames ? ['name' => $strengthNames] : null,
                'farmula' => $formulaNames,
                'price' => $defaultPrice,
                'default_category_id' => $defaultCategoryId,
                'preference' => $preferenceInfo['preference']->slug,
                'including_tax' => $preferenceInfo['including_tax'],
                'categories' => $categories,
                'discount' => $product->discount ?? 0,
                'discount_percent' => $product->discount_percent ?? 0,
                'lock_max_discount' => (bool) $product->lock_max_discount,
                'available_base_stock' => $actuallyAvailable
            ];
        })->filter()->values();

        return response()->json($responseData);
    }

    /**
     * Get price for specific category (for POS category dropdown changes)
     */
    public function getCategoryPrice(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'category_id' => 'required|exists:categories,id'
        ]);

        // Check stock availability first
        if (!$this->hasStockAvailable($request->product_id)) {
            return response()->json([
                'price' => 0,
                'out_of_stock' => true,
                'message' => 'No stock available'
            ]);
        }
        // Get sale price preference
        $preferenceInfo = $this->getSalePricePreference($request->product_id);


        // Calculate price for selected category
        $price = $this->calculateSalePrice($request->product_id, $request->category_id, $preferenceInfo);

        return response()->json([
            'price' => $price,
            'preference' => $preferenceInfo['preference']->slug,
            'including_tax' => $preferenceInfo['including_tax']
        ]);
    }

    public function handlePOSQuantityChange(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:1',
            'category_id' => 'nullable|exists:categories,id',
            'from_base_stock_sale_price_id' => 'nullable|exists:base_stock_sale_price,id'
        ]);

        $productId = $request->product_id;
        $requestedQuantity = $request->quantity;
        $selectedCategoryId = $request->category_id;
        $fromStockId = $request->from_base_stock_sale_price_id;

        try {
            // Get current cart items for this product
            if ($request->has('current_cart')) {
                $cart = json_decode($request->current_cart, true) ?: [];
            } else {
                $cart = $request->session()->get('pos_cart', []);
            }
            $cartProductItems = array_filter($cart, function ($item) use ($productId) {
                return $item['product_id'] == $productId;
            });

            // Calculate already reserved quantities (per batch ID, falling back to price group)
            $alreadyReserved = $this->calculateAlreadyReserved($cartProductItems, $productId);

            // Net out stock already held in OTHER sales-persons' in-progress
            // carts too (this browser's own cart is already accounted for
            // above) — see PosCartReservation::reservedBucketsForProduct().
            $othersReserved = \App\Models\PosCartReservation::reservedBucketsForProduct($productId, auth()->id());
            $alreadyReserved = $this->mergeReservedBuckets($alreadyReserved, $othersReserved);

            // Get product parameters
            $parameters = ProductParameter::where('product_id', $productId)->get();
            if ($parameters->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No product parameters found.'
                ]);
            }

            // If no category selected, use base category
            if (!$selectedCategoryId) {
                $selectedCategoryId = $this->getBaseCategoryId($productId);
            }

            // Convert requested quantity to base quantity
            $baseQuantityRequired = $this->convertToBaseQuantityOptimized($productId, $selectedCategoryId, $requestedQuantity, $parameters);

            // Get available stock with price grouping and cart awareness
            $availableStock = $this->getAvailableStockWithPriceGrouping(
                $productId,
                $selectedCategoryId,
                $alreadyReserved,
                $fromStockId,
                $request->input('lot_selection_mode')
            );

            // Check if enough stock is available
            $totalAvailableBase = $availableStock['total_base_qty'];

            $availableInSelected = $this->convertFromBaseQuantityOptimized(
                $productId,
                $selectedCategoryId,
                $totalAvailableBase,
                $parameters
            );

            $selectedCategoryName = \App\Models\Category::find($selectedCategoryId)->name ?? 'Category';
            $isFractional = ($availableInSelected > 0) && (fmod(round($availableInSelected, 4), 1) != 0);

            if ($baseQuantityRequired > $totalAvailableBase) {
                if ($totalAvailableBase <= 0) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Insufficient stock. Out of stock completely."
                    ]);
                }

                $responseRows = [];
                $preferenceInfo = $this->getSalePricePreference($productId);
                $preferenceSlug = $preferenceInfo['preference']->slug ?? 'static-price';

                $workingPriceGroups = $availableStock['price_groups'];

                if ($preferenceSlug !== 'stock-wise-price') {
                    // Decompose the available base stock globally 
                    $decomposed = $this->decomposeStockToLargestCategories($totalAvailableBase, $parameters, $productId);
                    
                    foreach ($decomposed as $alloc) {
                        $catId = $alloc['category_id'];
                        $qtyToAllocate = $alloc['quantity'];
                        $baseQtyToAllocate = $alloc['base_qty'];
                        
                        $price = $this->calculateSalePrice($productId, $catId, $preferenceInfo);
                        $responseRows[] = [
                            'product_id' => $productId,
                            'quantity' => $qtyToAllocate,
                            'unit_price' => round($price, 4),
                            'category_id' => $catId,
                            'base_qty' => $baseQtyToAllocate,
                            'price_group' => number_format((float)$price, 2, '.', ''),
                            'base_stock_sale_price_id' => null,
                            'discount_selected_type' => 'percent',
                            'discount_percent' => 0,
                            'discount_amount' => 0,
                            'max_discount_percent' => 0,
                            'max_discount_amount' => 0,
                        ];
                    }
                } else {
                    // stock-wise-price: decompose EACH internal Price Group independently to avoid non-round batch boundaries
                    foreach ($workingPriceGroups as $priceKey => &$group) {
                        foreach ($group['rows'] as &$row) {
                            $rowBaseAvailable = $row['base_qty'];
                            if ($rowBaseAvailable <= 0) continue;

                            $decomposedRow = $this->decomposeStockToLargestCategories($rowBaseAvailable, $parameters, $productId);

                            foreach ($decomposedRow as $alloc) {
                                $catId = $alloc['category_id'];
                                $qtyToAllocate = $alloc['quantity'];
                                $baseQtyToAllocate = $alloc['base_qty'];

                                $unitPrice = 0;
                                if (isset($row['original_stock'])) {
                                    $unitPrice = $this->calculateUnitPriceForStockRow($row['original_stock'], $catId);
                                } else {
                                    $unitPrice = $row['unit_price'];
                                }
                                
                                $responseRows[] = [
                                    'product_id' => $productId,
                                    'quantity' => $qtyToAllocate,
                                    'unit_price' => round($unitPrice, 4),
                                    'category_id' => $catId,
                                    'base_qty' => $baseQtyToAllocate,
                                    'price_group' => number_format((float)$unitPrice, 2, '.', ''),
                                    'base_stock_sale_price_id' => $row['id'] ?? null,
                                    'discount_selected_type' => 'percent',
                                    'discount_percent' => 0,
                                    'discount_amount' => 0,
                                    'max_discount_percent' => 0,
                                    'max_discount_amount' => 0,
                                ];
                            }
                        }
                    }
                }

                $msg = "Insufficient stock. Available: " . number_format($availableInSelected, 2) . " " . $selectedCategoryName . "(s). Adding maximum decomposed stock instead.";

                return response()->json([
                    'status' => 'partial',
                    'message' => $msg,
                    'available_quantity' => $availableInSelected,
                    'rows' => $responseRows
                ]);
            }

            // Get preference
            $preferenceInfo = $this->getSalePricePreference($productId);
            $preferenceSlug = $preferenceInfo['preference']->slug ?? 'static-price';

            // If NOT stock-wise-price
            if ($preferenceSlug !== 'stock-wise-price') {
                $price = $this->calculateSalePrice($productId, $selectedCategoryId, $preferenceInfo);
                return response()->json([
                    'status' => 'ok',
                    'rows' => [[
                        'product_id' => $productId,
                        'quantity' => $requestedQuantity,
                        'unit_price' => $price,
                        'category_id' => $selectedCategoryId,
                        'base_qty' => $baseQuantityRequired,
                    ]]
                ]);
            }

            // Handle stock-wise-price with PRICE GROUPING
            return $this->handlePriceGroupedFIFO(
                $productId,
                $selectedCategoryId,
                $baseQuantityRequired,
                $requestedQuantity,
                $availableStock,
                $preferenceInfo,
                $parameters
            );
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error checking stock: ' . $e->getMessage()
            ]);
        }
    }

    function getAvailableStockWithPriceGrouping($productId, $selectedCategoryId, $alreadyReserved, $fromStockId = null, $lotMode = null)
    {
        // Sellable lots in the line's lot order (FEFO/FIFO, decision D1) via the shared LotAllocator.
        $settings = app(\App\Services\Sales\SalePreferenceResolver::class)->forProduct((int) $productId);
        $lotMode = in_array($lotMode, \App\Services\Sales\SaleSettings::LOT_MODES, true) ? $lotMode : $settings->lotMode;

        $stockRows = app(\App\Services\Sales\LotAllocator::class)->sellableLots((int) $productId, $lotMode);

        if ($fromStockId && $lotMode === \App\Services\Sales\SaleSettings::LOT_FIFO) {
            // "Continue after this lot" only has a meaning in purchase (id) order.
            $stockRows = $stockRows->filter(fn ($row) => $row->id >= $fromStockId)->values();
        }

        // Group by price (string key: two decimals) and subtract already reserved
        $priceGroups = [];
        $totalBaseQty = 0;

        $preferenceInfo = $this->getSalePricePreference($productId);
        $preferenceSlug = $preferenceInfo['preference']->slug ?? 'static-price';

        $totalReservedGeneric = 0;
        foreach ($alreadyReserved['per_price'] as $qty) {
            $totalReservedGeneric += $qty;
        }

        foreach ($stockRows as $stockRow) {
            // Calculate unit price for this row for the SELECTED category
            $unitPrice = $this->calculateUnitPriceForStockRow($stockRow, $selectedCategoryId);

            // Normalize price key as string with 2 decimals to match frontend .toFixed(2)
            $priceKey = number_format((float)$unitPrice, 2, '.', '');

            $remainingInRow = $stockRow->remaining_base_stock;
            $availableQty = $remainingInRow;

            // 1. Try to deduct direct batch reservation first
            $batchId = $stockRow->id;
            if (isset($alreadyReserved['per_batch'][$batchId]) && $alreadyReserved['per_batch'][$batchId] > 0) {
                $deduct = min($availableQty, $alreadyReserved['per_batch'][$batchId]);
                $availableQty -= $deduct;
                $alreadyReserved['per_batch'][$batchId] -= $deduct;
            }

            // 2. Fallback to generic price group reservation
            if ($preferenceSlug !== 'stock-wise-price') {
                if ($availableQty > 0 && $totalReservedGeneric > 0) {
                    $deduct = min($availableQty, $totalReservedGeneric);
                    $availableQty -= $deduct;
                    $totalReservedGeneric -= $deduct;
                }
            } else {
                if ($availableQty > 0 && isset($alreadyReserved['per_price'][$priceKey]) && $alreadyReserved['per_price'][$priceKey] > 0) {
                    $deduct = min($availableQty, $alreadyReserved['per_price'][$priceKey]);
                    $availableQty -= $deduct;
                    $alreadyReserved['per_price'][$priceKey] -= $deduct;
                }
            }

            if ($availableQty > 0) {
                if (!isset($priceGroups[$priceKey])) {
                    $priceGroups[$priceKey] = [
                        'base_qty' => 0,
                        'rows' => []
                    ];
                }

                $priceGroups[$priceKey]['base_qty'] += $availableQty;
                $priceGroups[$priceKey]['rows'][] = [
                    'id' => $stockRow->id,
                    'base_qty' => $availableQty,
                    'unit_price' => (float)$unitPrice,
                    'original_stock' => $stockRow
                ];

                $totalBaseQty += $availableQty;
            }
        }

        return [
            'price_groups' => $priceGroups,
            'total_base_qty' => $totalBaseQty
        ];
    }

    function calculateAlreadyReserved($cartItems, $productId = null)
    {
        $reserved = [
            'per_batch' => [],
            'per_price' => []
        ];

        foreach ($cartItems as $item) {
            // base_qty is recomputed authoritatively from quantity +
            // category_id (the same conversion used everywhere else),
            // rather than trusted from the posted cart line — the client's
            // own base_qty is accumulated across several separate JS
            // operations (add, merge-same-price-rows, qty-edit) and can
            // drift from the true quantity×multiplier relationship. See
            // PosCartReservation::syncForUser()/POSController::saveCartSession()
            // for the same principle applied to the persisted cross-user
            // reservation table — this is the equivalent fix for this
            // browser's OWN cart, netted here against its own stock query.
            $categoryId = $item['category_id'] ?? null;
            $qty = isset($item['quantity']) ? (float) $item['quantity'] : null;
            $baseQty = null;

            if ($productId && $categoryId && $qty !== null && $qty > 0) {
                try {
                    $baseQty = (float) $this->convertToBaseQuantityOptimized($productId, $categoryId, $qty);
                } catch (\Exception $e) {
                    $baseQty = null;
                }
            }

            // Fall back to the posted base_qty only when authoritative
            // recomputation isn't possible (e.g. no category_id present).
            if ($baseQty === null) {
                if (!isset($item['base_qty'])) continue;
                $baseQty = (float) $item['base_qty'];
            }

            if ($baseQty <= 0) continue;

            // Preferably map by exact batch ID
            if (!empty($item['base_stock_sale_price_id'])) {
                $batchId = $item['base_stock_sale_price_id'];
                $reserved['per_batch'][$batchId] = ($reserved['per_batch'][$batchId] ?? 0) + $baseQty;
                continue; // mapped by batch ID confidently, no need to add to generic price group
            }

            // Fallback: Accept both 'unit_price' or 'price' keys in session payload to be robust
            $unitPrice = null;
            if (isset($item['unit_price'])) {
                $unitPrice = $item['unit_price'];
            } elseif (isset($item['price'])) {
                $unitPrice = $item['price'];
            } elseif (isset($item['price_group'])) {
                $unitPrice = $item['price_group'];
            }

            if ($unitPrice !== null) {
                // normalize key to 2-decimal string (same format used elsewhere)
                $priceKey = number_format((float)$unitPrice, 2, '.', '');
                $reserved['per_price'][$priceKey] = ($reserved['per_price'][$priceKey] ?? 0) + $baseQty;
            }
        }

        return $reserved;
    }

    /**
     * Sums two 'per_batch'/'per_price' reservation-bucket maps (same shape
     * as calculateAlreadyReserved()) by key, so cross-user holds
     * (PosCartReservation::reservedBucketsForProduct()) can be folded into
     * this browser's own already-reserved amounts before either is handed
     * to getAvailableStockWithPriceGrouping() — that function's netting
     * logic and every downstream status/message path are otherwise
     * untouched.
     */
    private function mergeReservedBuckets(array $mine, array $others): array
    {
        foreach (['per_batch', 'per_price'] as $bucketKey) {
            foreach ($others[$bucketKey] ?? [] as $key => $qty) {
                $mine[$bucketKey][$key] = ($mine[$bucketKey][$key] ?? 0) + $qty;
            }
        }

        return $mine;
    }

    function handlePriceGroupedFIFO(
        $productId,
        $selectedCategoryId,
        $baseQuantityRequired,
        $requestedQuantity,
        $availableStock,
        $preferenceInfo,
        $parameters
    ) {
        $priceGroups = $availableStock['price_groups'];
        $remainingBaseQty = $baseQuantityRequired;
        $allocatedRows = [];

        foreach ($priceGroups as $priceKey => $group) {

            foreach ($group['rows'] as $row) {

                if ($remainingBaseQty <= 0) break;

                $takeFromRow = min($remainingBaseQty, $row['base_qty']);

                if ($takeFromRow <= 0) continue;

                $selectedQty = $this->convertFromBaseQuantityOptimized(
                    $productId,
                    $selectedCategoryId,
                    $takeFromRow,
                    $parameters
                );

                $allocatedRows[] = [
                    'product_id' => $productId,
                    'quantity' => round($selectedQty, 4),
                    'unit_price' => round($row['unit_price'], 4),
                    'category_id' => $selectedCategoryId,
                    'base_qty' => $takeFromRow,
                    'price_group' => number_format($row['unit_price'], 2, '.', ''),
                    'base_stock_sale_price_id' => $row['id'], // ✅ IMPORTANT
                ];

                $remainingBaseQty -= $takeFromRow;
            }

            if ($remainingBaseQty <= 0) break;
        }

        if ($remainingBaseQty > 0) {
            return response()->json([
                'status' => 'error',
                'message' => "Not enough stock available."
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'rows' => $allocatedRows
        ]);
    }

    function calculateUnitPriceForStockRow($stockRow, $selectedCategoryId)
    {
        $settings = app(\App\Services\Sales\SalePreferenceResolver::class)->forProduct((int) $stockRow->product_id);

        return app(\App\Services\Sales\SalePriceCalculator::class)
            ->lotUnitPrice($stockRow, $selectedCategoryId ? (int) $selectedCategoryId : null, $settings->includeTax);
    }


    /**
     * Optimized conversion from selected category to base category quantity
     */
    public function convertToBaseQuantityOptimized($productId, $fromCategoryId, $quantity, $parameters = null)
    {
        if ($parameters === null) {
            $parameters = ProductParameter::where('product_id', $productId)->get();
        }

        $baseCategoryId = $this->getBaseCategoryId($productId);

        // If it's already base category, return as is
        if ($fromCategoryId == $baseCategoryId) {
            return $quantity;
        }

        // Try simple direct conversion first
        $simpleResult = $this->simpleCategoryConversion($productId, $fromCategoryId, $baseCategoryId, $quantity, $parameters);
        if ($simpleResult !== null) {
            return $simpleResult;
        }

        // Fall back to BFS path finding
        $hierarchyMap = $this->buildCategoryHierarchyMap($parameters);
        $path = $this->findCategoryPath($fromCategoryId, $baseCategoryId, $hierarchyMap);

        if (empty($path)) {
            throw new \Exception("Cannot find conversion path from category $fromCategoryId to base category");
        }

        $conversionFactor = 1;
        foreach ($path as $step) {
            $conversionFactor *= $step['quantity'];
        }

        return $quantity * $conversionFactor;
    }

    /**
     * Optimized conversion from base category to selected category
     */
    public function convertFromBaseQuantityOptimized($productId, $toCategoryId, $baseQuantity, $parameters = null)
    {
        if ($parameters === null) {
            $parameters = ProductParameter::where('product_id', $productId)->get();
        }

        $baseCategoryId = $this->getBaseCategoryId($productId);

        // If it's base category, return as is
        if ($toCategoryId == $baseCategoryId) {
            return $baseQuantity;
        }

        // Build category hierarchy map
        $hierarchyMap = $this->buildCategoryHierarchyMap($parameters);

        // Find path from base category to selected category
        $path = $this->findCategoryPath($baseCategoryId, $toCategoryId, $hierarchyMap);

        if (empty($path)) {
            throw new \Exception("Cannot find conversion path from base category to category $toCategoryId");
        }

        // Calculate conversion factor along the path
        $conversionFactor = 1;
        foreach ($path as $step) {
            $conversionFactor *= $step['quantity'];
        }

        return $baseQuantity / $conversionFactor;
    }

    /**
     * Build a map of category relationships for efficient path finding
     */
    public function buildCategoryHierarchyMap($parameters)
    {
        $hierarchyMap = [];

        foreach ($parameters as $param) {
            // Skip self-relationships
            if ($param->parent_category_id == $param->child_category_id) {
                continue;
            }

            $hierarchyMap[$param->parent_category_id][] = [
                'child_id' => $param->child_category_id,
                'quantity' => $param->quantity
            ];

            // Also create reverse mapping for bidirectional traversal
            $hierarchyMap[$param->child_category_id][] = [
                'child_id' => $param->parent_category_id,
                'quantity' => $param->quantity,
                'is_reverse' => true
            ];
        }

        return $hierarchyMap;
    }

    /**
     * Find path between two categories using BFS to avoid infinite loops
     */
    public function findCategoryPath($startCategoryId, $targetCategoryId, $hierarchyMap)
    {
        if ($startCategoryId == $targetCategoryId) {
            return [];
        }

        $visited = [];
        $queue = new \SplQueue();
        $queue->enqueue(['category_id' => $startCategoryId, 'path' => []]);
        $visited[$startCategoryId] = true;

        while (!$queue->isEmpty()) {
            $current = $queue->dequeue();
            $currentCategoryId = $current['category_id'];
            $currentPath = $current['path'];

            if (!isset($hierarchyMap[$currentCategoryId])) {
                continue;
            }

            foreach ($hierarchyMap[$currentCategoryId] as $connection) {
                $nextCategoryId = $connection['child_id'];

                if (isset($visited[$nextCategoryId])) {
                    continue;
                }

                $newPath = $currentPath;
                $newPath[] = [
                    'from' => $currentCategoryId,
                    'to' => $nextCategoryId,
                    'quantity' => $connection['quantity'],
                    'is_reverse' => $connection['is_reverse'] ?? false
                ];

                if ($nextCategoryId == $targetCategoryId) {
                    return $newPath;
                }

                $visited[$nextCategoryId] = true;
                $queue->enqueue(['category_id' => $nextCategoryId, 'path' => $newPath]);

                // Safety check - prevent infinite loops
                if (count($visited) > 50) {
                    throw new \Exception("Category path finding taking too long - possible circular reference");
                }
            }
        }

        return null; // No path found
    }


    public function simpleCategoryConversion($productId, $fromCategoryId, $toCategoryId, $quantity, $parameters)
    {
        // Try to find direct relationship first
        $directParam = $parameters->where('parent_category_id', $fromCategoryId)
            ->where('child_category_id', $toCategoryId)
            ->first();

        if ($directParam) {
            return $quantity * $directParam->quantity;
        }

        // Try reverse direct relationship
        $reverseParam = $parameters->where('parent_category_id', $toCategoryId)
            ->where('child_category_id', $fromCategoryId)
            ->first();

        if ($reverseParam) {
            return $quantity / $reverseParam->quantity;
        }

        return null; // No direct relationship found
    }

    public function decomposeStockToLargestCategories($baseQty, $parameters, $productId) {
        $allocation = [];
        $remaining = round($baseQty, 4);

        $allCategoryIds = [];
        foreach ($parameters as $param) {
            if ($param->parent_category_id) {
                $allCategoryIds[] = $param->parent_category_id;
            }
            if ($param->child_category_id) {
                $allCategoryIds[] = $param->child_category_id;
            }
        }
        $uniqueCategoryIds = array_unique($allCategoryIds);

        $categories = [];
        foreach ($uniqueCategoryIds as $catId) {
            try {
                $baseMultiplier = $this->convertToBaseQuantityOptimized($productId, $catId, 1, $parameters);
                $categories[] = [
                    'id' => $catId,
                    'base_quantity' => $baseMultiplier
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Sort descending by base_quantity mapping
        usort($categories, function($a, $b) {
            return $b['base_quantity'] <=> $a['base_quantity'];
        });

        // Drop entries with no real size mapping
        $categories = array_filter($categories, function($cat) { return $cat['base_quantity'] > 0; });
        $categories = array_values($categories);

        $lastIter = count($categories) - 1;

        foreach ($categories as $index => $cat) {
            if ($remaining <= 0) break;
            
            $bQty = $cat['base_quantity'];
            
            if ($index === $lastIter) {
                // For the smallest category, just take whatever is left to support fractions
                $qty = round($remaining / $bQty, 4);
            } else {
                $qty = floor(round($remaining / $bQty, 4));
            }

            if ($qty > 0) {
                $allocBase = $qty * $bQty;
                $allocation[] = [
                    'category_id' => $cat['id'],
                    'quantity' => $qty,
                    'base_qty' => $allocBase
                ];
                $remaining -= $allocBase;
                $remaining = round($remaining, 4);
            }
        }
        return $allocation;
    }
    public function priceSummary($id)
    {
        $product = Product::findOrFail($id);
        $params = ProductParameter::where('product_id', $id)->get();
        
        $preferenceInfo = $this->getSalePricePreference($id);
        $pref = $preferenceInfo['preference'];
        $prefType = $preferenceInfo['type'];
        $isTaxIncluded = $preferenceInfo['including_tax'];

        $prefName = $pref ? ($pref->name ?? $pref->preference ?? 'Static Price') : 'Static Price';
        $activePrefStr = $prefName;
        if ($prefType === 'global') {
            $activePrefStr .= ' (Global)';
        } elseif ($prefType === 'default') {
            $activePrefStr .= ' (Default)';
        }
        $activePrefSlug = $pref ? $pref->slug : 'static-price';

        $summary = [];

        $latestPurchase = Purchase::where('product_id', $id)->latest()->first();
        $basePrice = \App\Models\BaseStockSalePrice::where('product_id', $id)->where('remaining_base_stock', '>', 0)->oldest()->first();
        if (!$basePrice) {
            $basePrice = \App\Models\BaseStockSalePrice::where('product_id', $id)->latest()->first();
        }

        $oldPurchase = null;
        if ($basePrice && $basePrice->purchase_id) {
            $oldPurchase = \App\Models\Purchase::find($basePrice->purchase_id);
        }

        foreach ($params as $param) {
            $catId = $param->child_category_id;
            $catName = Category::find($catId)->name ?? 'Unknown';

            $multiplier = 1;
            try {
                $multiplier = $this->convertToBaseQuantityOptimized($id, $catId, 1, $params);
            } catch (\Exception $e) {
                $multiplier = 1; 
            }

            $stockPurchasePriceRaw = 0;
            $stockPurchaseTaxRaw = 0;
            $stockSalePriceRaw = 0;
            $stockSaleTaxRaw = 0;

            if ($latestPurchase) {
                $stockPurchasePriceRaw = ((float)($latestPurchase->base_unit_purchase_price ?? 0)) * $multiplier;
                $stockPurchaseTaxRaw = ((float)($latestPurchase->base_unit_purchase_tax_price ?? 0)) * $multiplier;
                $stockSalePriceRaw = ((float)($latestPurchase->base_unit_sale_price ?? 0)) * $multiplier;
                $stockSaleTaxRaw = ((float)($latestPurchase->base_unit_sale_tax_price ?? 0)) * $multiplier;
            }

            $fifoSalePriceRaw = 0;
            $fifoSaleTaxRaw = 0;
            $fifoPurchasePriceRaw = 0;
            $fifoPurchaseTaxRaw = 0;

            if ($basePrice) {
                $fifoSalePriceRaw = ((float)($basePrice->base_category_unit_sale_price ?? 0)) * $multiplier;
                $fifoSaleTaxRaw = ((float)($basePrice->base_category_unit_sale_tax_price ?? 0)) * $multiplier;
                
                if ($oldPurchase) {
                    $fifoPurchasePriceRaw = ((float)($oldPurchase->base_unit_purchase_price ?? 0)) * $multiplier;
                    $fifoPurchaseTaxRaw = ((float)($oldPurchase->base_unit_purchase_tax_price ?? 0)) * $multiplier;
                } else {
                    $fifoPurchasePriceRaw = $stockPurchasePriceRaw;
                    $fifoPurchaseTaxRaw = $stockPurchaseTaxRaw;
                }
            }

            $activeSalePriceRaw = 0;
            $activeSaleTaxRaw = 0;
            $activePurchasePriceRaw = 0;
            $activePurchaseTaxRaw = 0;

            if ($activePrefSlug == 'static-price') {
                $activeSalePriceRaw = (float)($param->static_category_unit_sale_price ?? 0);
                $activePurchasePriceRaw = (float)($param->static_category_unit_purchase_price ?? 0);
            } elseif ($activePrefSlug == 'stock-wise-price') {
                $activeSalePriceRaw = $fifoSalePriceRaw;
                $activeSaleTaxRaw = $fifoSaleTaxRaw;
                $activePurchasePriceRaw = $fifoPurchasePriceRaw;  
                $activePurchaseTaxRaw = $fifoPurchaseTaxRaw;
            } elseif ($activePrefSlug == 'previous-inventory-price') {
                $activeSalePriceRaw = $stockSalePriceRaw;
                $activeSaleTaxRaw = $stockSaleTaxRaw;
                $activePurchasePriceRaw = $stockPurchasePriceRaw;
                $activePurchaseTaxRaw = $stockPurchaseTaxRaw;
            }

            // Fallback to static price if active calculated price is 0 (e.g., no stock data yet for newly imported products)
            if ($activeSalePriceRaw == 0 && $activePurchasePriceRaw == 0) {
                $activeSalePriceRaw = (float)($param->static_category_unit_sale_price ?? 0);
                $activePurchasePriceRaw = (float)($param->static_category_unit_purchase_price ?? 0);
                $activeSaleTaxRaw = 0;
                $activePurchaseTaxRaw = 0;
            }

            $finalActiveSale = $isTaxIncluded ? ($activeSalePriceRaw + $activeSaleTaxRaw) : $activeSalePriceRaw;
            $finalActivePurchase = $isTaxIncluded ? ($activePurchasePriceRaw + $activePurchaseTaxRaw) : $activePurchasePriceRaw;

            $summary[] = [
                'category' => $catName,
                'static_purchase' => number_format($param->static_category_unit_purchase_price ?? 0, 2),
                'static_sale' => number_format($param->static_category_unit_sale_price ?? 0, 2),
                'stock_purchase' => number_format($stockPurchasePriceRaw, 2),
                'stock_purchase_tax' => number_format($stockPurchaseTaxRaw, 2),
                'stock_sale' => number_format($stockSalePriceRaw, 2),
                'stock_sale_tax' => number_format($stockSaleTaxRaw, 2),
                'active_purchase_price' => number_format($finalActivePurchase, 2),
                'active_purchase_tax' => number_format($activePurchaseTaxRaw, 2),
                'active_sale_price' => number_format($finalActiveSale, 2),
                'active_sale_tax' => number_format($activeSaleTaxRaw, 2)
            ];
        }

        // Fetch ALL batches for stock-wise table
        $batches = \App\Models\BaseStockSalePrice::where('product_id', $id)->oldest()->get();
        $batchSummary = [];
        $batchCounter = 1;
        
        $fallbackPurchase = \App\Models\Purchase::where('product_id', $id)->latest()->first();

        foreach ($batches as $batch) {
            $dt = $batch->created_at ? $batch->created_at->format('Y-m-d H:i') : '-';
            
            $purchase = $batch->purchase_id ? \App\Models\Purchase::find($batch->purchase_id) : null;
            $activePurchase = $purchase ?: $fallbackPurchase;

            // Generate price map for this batch
            $catPrices = [];
            $catPurchasePrices = [];
            foreach ($params as $param) {
                $catId = $param->child_category_id;
                $catName = Category::find($catId)->name ?? 'Unknown';
                try {
                    $multiplier = $this->convertToBaseQuantityOptimized($id, $catId, 1, $params);
                } catch (\Exception $e) {
                    $multiplier = 1;
                }
                
                $price = ((float)($batch->base_category_unit_sale_price ?? 0)) * $multiplier;
                $tax = ((float)($batch->base_category_unit_sale_tax_price ?? 0)) * $multiplier;
                $final = $isTaxIncluded ? ($price + $tax) : $price;
                $catPrices[] = $catName . ': ' . number_format($final, 2);

                $purP = 0;
                $purTax = 0;
                if ($activePurchase) {
                    $purP = ((float)($activePurchase->base_unit_purchase_price ?? 0)) * $multiplier;
                    $purTax = ((float)($activePurchase->base_unit_purchase_tax_price ?? 0)) * $multiplier;
                }
                $finalPur = $isTaxIncluded ? ($purP + $purTax) : $purP;
                $catPurchasePrices[] = $catName . ': ' . number_format($finalPur, 2);
            }

            $is_expired = $batch->expiry_date && \Illuminate\Support\Carbon::parse($batch->expiry_date)->startOfDay()->lt(now()->startOfDay());
            $is_zero_qty = $batch->remaining_base_stock <= 0;

            $batchNoStr = $batchCounter++;
            $bNo = $purchase ? $purchase->batch_no : null;
            $iNo = $purchase ? $purchase->invoice_no : null;
            
            $suffix = [];
            if ($bNo) $suffix[] = $bNo;
            if ($iNo) $suffix[] = $iNo;
            
            if (count($suffix) > 0) {
                $batchNoStr .= ' - ' . implode(' / ', $suffix);
            }

            $catNameForBatch = 'Unknown';
            if ($batch->category_id) {
                $catNameForBatch = \App\Models\Category::find($batch->category_id)->name ?? 'Unknown';
            }

            $batchSummary[] = [
                'date' => $dt,
                'batch_no' => $batchNoStr,
                'remaining_stock' => $batch->remaining_base_stock,
                'category_name' => $catNameForBatch,
                'prices' => implode(' | ', $catPrices),
                'purchase_prices' => implode(' | ', $catPurchasePrices),
                'is_expired' => $is_expired,
                'is_zero_qty' => $is_zero_qty
            ];
        }

        return response()->json([
            'product_name' => $product->product_name,
            'active_preference' => $activePrefStr,
            'tax_included' => $isTaxIncluded ? 'Yes' : 'No',
            'summary' => $summary,
            'batches' => $batchSummary
        ]);
    }

    // quickStockModal removed: products/{id}/quick-stock opens the purchase form (Roadmap 5.5c).
}
