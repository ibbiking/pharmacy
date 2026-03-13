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
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'products';
        if ($request->ajax()) {
            $products = Product::latest();
            return DataTables::of($products)
                ->addColumn('product_name', function ($product) {
                    $image = '';
                    $image = null;
                    if (!empty($product->image)) {
                        $image = '<span class="avatar avatar-sm mr-2">
                            <img class="avatar-img" src="' . asset("storage/purchases/" . $product->image) . '" alt="image">
                            </span>';
                    }
                    return $product->product_name . ' ' . $image;
                })->addColumn('strength', function ($product) {
                    return $product->strength->name ?? '-';
                })->addColumn('type', function ($product) {
                    return $product->type->name ?? '-';
                })->addColumn('company', function ($product) {
                    return $product->company->name ?? '-';
                })
                ->addColumn('farmula', function ($product) {
                    return $product->farmula->name ?? '-';
                })

                // ->addColumn('category', function ($product) {
                //     $category = null;
                //     if (!empty($product->purchase->category)) {
                //         $category = $product->purchase->category->name;
                //     }
                //     return $category;
                // })
                // ->addColumn('price', function ($product) {
                //     return settings('app_currency', '$') . ' ' . $product->price;
                // })
                // ->addColumn('quantity', function ($product) {
                //     if (!empty($product->purchase)) {
                //         return $product->purchase->quantity;
                //     }
                // })
                // ->addColumn('expiry_date', function ($product) {
                //     if (!empty($product->purchase)) {
                //         return date_format(date_create($product->purchase->expiry_date), 'd M, Y');
                //     }
                // })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("products.edit", $row->id) . '" class="editbtn">
        <button class="btn btn-info"><i class="fas fa-edit"></i></button>
    </a>';

                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('products.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn">
        <button class="btn btn-danger"><i class="fas fa-trash"></i></button>
    </a>';

                    $paramBtn = '<a href="' . route("products.parameters", $row->id) . '" 
        class="btn btn-warning" title="Manage Product Parameters">
        <i class="fas fa-sliders-h"></i>
    </a>';

                    $catBtn = '<a href="' . route("product-categories.create", ["product_id" => $row->id]) . '" 
        class="btn btn-success" title="Add Product Category Relation">
        <i class="fas fa-sitemap"></i>
    </a>';

                    $prefBtn = '<a href="' . route("products.sale-price-preferences", $row->id) . '" 
    class="btn btn-primary" title="Manage Sale Price Preference">
    <i class="fas fa-dollar-sign"></i>
</a>';

                    $stockBtn = '<button class="btn btn-secondary show-stock" 
        data-id="' . $row->id . '" 
        title="View Stock">
        <i class="fas fa-boxes"></i>
    </button>';

                    return $editbtn . ' ' . $deletebtn . ' ' . $catBtn . ' ' . $paramBtn . ' ' . $prefBtn . ' ' . $stockBtn;
                })
                ->rawColumns(['product_name', 'action'])
                ->make(true);
        }
        return view('admin.products.index', compact(
            'title'
        ));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $title = 'add product';
        $companies   = Company::all();
        $farmulas    = Farmula::all();
        $productTypes = \App\Models\ProductType::all();
        $strengths    = \App\Models\Strength::all();

        return view('admin.products.create', compact(
            'title',
            'companies',
            'farmulas',
            'productTypes',
            'strengths'
        ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'product_name'     => 'required|max:200',
            'description'      => 'nullable|max:255',
            'company_id'       => 'required|exists:companies,id',
            'farmula_id'       => 'required|exists:farmulas,id',
            'product_type_id'  => 'required|exists:product_types,id',
            'strength_id'      => 'required|exists:strengths,id',
            'barcode'          => 'nullable|max:100',
            'discount'         => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack'          => 'nullable',
        ]);

        // get default preference by slug
        $defaultPreference = ProductPreference::where('slug', 'static-price')->first();

        Product::create([
            'product_name'              => $request->product_name,
            'description'               => $request->description,
            'company_id'                => $request->company_id,
            'farmula_id'                => $request->farmula_id,
            'product_type_id'           => $request->product_type_id,
            'strength_id'               => $request->strength_id,
            'sale_price_preference_id'  =>  null, // $defaultPreference->id ?? null, // set default
            'barcode'                   => $request->barcode,
            'discount'                  => $request->discount ?? 0,
            'discount_percent'  => $request->discount_percent ?? 0,
            'lock_max_discount'         => $request->has('lock_max_discount'),
            'rack'                   => $request->rack,
        ]);

        $notification = notify("Product has been added");
        return redirect()->route('products.index')->with($notification);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $title = 'edit product';
        $product->load(['company', 'farmula', 'type', 'strength']);

        $companies    = Company::all();
        $farmulas     = Farmula::all();
        $productTypes = \App\Models\ProductType::all();
        $strengths    = \App\Models\Strength::all();

        return view('admin.products.edit', compact(
            'title',
            'product',
            'companies',
            'farmulas',
            'productTypes',
            'strengths'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        $this->validate($request, [
            'product_name'     => 'required|max:200',
            'description'      => 'nullable|max:255',
            'company_id'       => 'required|exists:companies,id',
            'farmula_id'       => 'required|exists:farmulas,id',
            'product_type_id'  => 'required|exists:product_types,id',
            'strength_id'      => 'required|exists:strengths,id',
            'barcode'          => 'nullable|max:100',
            'discount'         => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack'          => 'nullable',
        ]);

        $product->update([
            'product_name'    => $request->product_name,
            'description'     => $request->description,
            'company_id'      => $request->company_id,
            'farmula_id'      => $request->farmula_id,
            'product_type_id' => $request->product_type_id,
            'strength_id'     => $request->strength_id,
            'barcode'                   => $request->barcode,
            'discount'                  => $request->discount ?? 0,
            'discount_percent'  => $request->discount_percent ?? 0,
            'lock_max_discount'         => $request->has('lock_max_discount'),
            'rack'                   => $request->rack,
        ]);
        $notification = notify('product has been updated');
        return redirect()->route('products.index')->with($notification);
    }

    /**
     * Display a listing of expired resources.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function expired(Request $request)
    {
        $title = "expired Products";
        if ($request->ajax()) {
            $products = Purchase::whereDate('expiry_date', '=', Carbon::now())->get();
            return DataTables::of($products)
                ->addColumn('product', function ($product) {
                    $image = '';
                    if (!empty($product->purchase)) {
                        $image = null;
                        if (!empty($product->purchase->image)) {
                            $image = '<span class="avatar avatar-sm mr-2">
                            <img class="avatar-img" src="' . asset("storage/purchases/" . $product->purchase->image) . '" alt="image">
                            </span>';
                        }
                        return $product->purchase->product . ' ' . $image;
                    }
                })

                ->addColumn('category', function ($product) {
                    $category = null;
                    if (!empty($product->purchase->category)) {
                        $category = $product->purchase->category->name;
                    }
                    return $category;
                })
                ->addColumn('price', function ($product) {
                    return settings('app_currency', '$') . ' ' . $product->price;
                })
                ->addColumn('quantity', function ($product) {
                    if (!empty($product->purchase)) {
                        return $product->purchase->quantity;
                    }
                })
                ->addColumn('expiry_date', function ($product) {
                    if (!empty($product->purchase)) {
                        return date_format(date_create($product->purchase->expiry_date), 'd M, Y');
                    }
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("products.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('products.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    // if (!auth()->user()->hasPermissionTo('edit-product')) {
                    //     $editbtn = '';
                    // }
                    // if (!auth()->user()->hasPermissionTo('destroy-purchase')) {
                    //     $deletebtn = '';
                    // }
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['product', 'action'])
                ->make(true);
        }

        return view('admin.products.expired', compact(
            'title',
        ));
    }

    /**
     * Display a listing of out of stock resources.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function outstock(Request $request)
    {
        $title = "outstocked Products";
        if ($request->ajax()) {
            $products = Purchase::where('quantity', '<=', 0)->get();
            return DataTables::of($products)
                ->addColumn('product', function ($product) {
                    $image = '';
                    if (!empty($product->purchase)) {
                        $image = null;
                        if (!empty($product->purchase->image)) {
                            $image = '<span class="avatar avatar-sm mr-2">
                            <img class="avatar-img" src="' . asset("storage/purchases/" . $product->purchase->image) . '" alt="image">
                            </span>';
                        }
                        return $product->purchase->product . ' ' . $image;
                    }
                })

                ->addColumn('category', function ($product) {
                    $category = null;
                    if (!empty($product->purchase->category)) {
                        $category = $product->purchase->category->name;
                    }
                    return $category;
                })
                ->addColumn('price', function ($product) {
                    return settings('app_currency', '$') . ' ' . $product->price;
                })
                ->addColumn('quantity', function ($product) {
                    if (!empty($product->purchase)) {
                        return $product->purchase->quantity;
                    }
                })
                ->addColumn('expiry_date', function ($product) {
                    if (!empty($product->purchase)) {
                        return date_format(date_create($product->purchase->expiry_date), 'd M, Y');
                    }
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("products.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('products.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    // if (!auth()->user()->hasPermissionTo('edit-product')) {
                    //     $editbtn = '';
                    // }
                    // if (!auth()->user()->hasPermissionTo('destroy-purchase')) {
                    //     $deletebtn = '';
                    // }
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['product', 'action'])
                ->make(true);
        }
        $product = Purchase::where('quantity', '<=', 0)->first();
        return view('admin.products.outstock', compact(
            'title',
        ));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return Product::findOrFail($request->id)->delete();
    }

    public function parameters(Product $product)
    {
        $title = 'Set Product Parameters';

        // get one product category (assume product has one base category link)
        $productCategory = \App\Models\ProductCategory::where('product_id', $product->id)->first();

        // find top parent category
        $baseCategory = $productCategory ? $productCategory->parentCategory : null;

        while ($baseCategory && $baseCategory->parentCategory) {
            $baseCategory = $baseCategory->parentCategory;
        }

        // build recursive children only for this product
        $children = $baseCategory
            ? \App\Models\Category::buildChildren($baseCategory->id, $product->id)
            : collect();

        // product parameters
        $parameters = $product->parameters()
            ->with(['parentCategory', 'childCategory'])
            ->get()
            ->keyBy('child_category_id');

        return view('admin.products.parameters', compact(
            'title',
            'product',
            'baseCategory',
            'children',
            'parameters'
        ));
    }

    public function storeParameters(Request $request, $productId)
    {
        foreach ($request->parameters as $param) {
            $parentCategoryId = $param['parent_category_id'] ?? 0; // default 0 if not set

            $record = ProductParameter::where('product_id', $productId)
                ->where('parent_category_id', $parentCategoryId)
                ->where('child_category_id', $param['child_category_id'])
                ->first();

            $data = [
                'quantity' => $param['quantity'] ?? 1, // parent = 1 by default
                'static_category_unit_sale_price' => $param['static_category_unit_sale_price'] ?? null,
            ];

            if ($record) {
                $record->update($data);
            } else {
                ProductParameter::create([
                    'product_id' => $productId,
                    'category_id' => $param['category_id'],
                    'parent_category_id' => $parentCategoryId,
                    'child_category_id' => $param['child_category_id'],
                    'quantity' => $param['quantity'] ?? 1,
                    'static_category_unit_sale_price' => $param['static_category_unit_sale_price'] ?? null,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Packaging parameters saved successfully.');
    }

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
    public function getStockSummary($productId)
    {
        $stock = ProductStock::where('product_id', $productId)->sum('current_stock');
        if (!$stock) {
            return [];
        }

        $params = ProductParameter::where('product_id', $productId)->get();

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
            $name = Category::find($catId)->name ?? 'Unknown';
            $result[] = [
                'category' => $name,
                'quantity' => number_format($qty, 2),
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

    public function salePricePreferences($productId)
    {
        $product = Product::findOrFail($productId);

        $preferences = ProductPreference::where('type', 'sale_price')->get();

        $title = 'Manage Sale Price Preference';

        return view('admin.products.sale_price_preferences.index', compact(
            'title',
            'product',
            'preferences'
        ));
    }

    public function storeSalePricePreferences(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'sale_price_preference_id' => 'exists:product_preferences,id',
        ]);

        $product->update([
            'sale_price_preference_id' => $request->sale_price_preference_id,
            'sale_price_including_tax' => $request->has('sale_price_including_tax'), // checkbox handling
        ]);

        $notification = notify('Sale price preference updated successfully.');

        return redirect()->route('products.index')->with($notification);
    }

    public function globalSalePricePreferences()
    {
        $preferences = Preference::where('type', 'sale_price')->get();

        $title = 'Global Sale Price Preferences';

        return view('admin.global_sale_price_preferences.index', compact(
            'title',
            'preferences'
        ));
    }

    public function storeGlobalSalePricePreferences(Request $request)
    {
        // $request->validate([
        //     'sale_price_preference_id' => 'required|exists:preferences,id',
        // ]);

        // Reset all statuses
        Preference::where('type', 'sale_price')
            ->whereIn('slug', ['static-price', 'stock-wise-price', 'previous-inventory-price'])
            ->update(['status' => false]);

        // Mark selected radio as active
        Preference::where('id', $request->sale_price_preference_id)
            ->update(['status' => true]);

        // Handle checkbox (sale-price-including-tax)
        Preference::where('slug', 'sale-price-including-tax')
            ->update(['status' => $request->has('sale_price_including_tax')]);

        $notification = notify("Global Sale Price Preferences updated successfully.");

        return redirect()->route('global-sale-price-preferences.index')->with($notification);
    }

    public function getSalePricePreference($productId)
    {
        $product = Product::find($productId);
        $productPreference = null;
        // Check product preference first
        if ($product && $product->sale_price_preference_id) {
            $productPreference = ProductPreference::find($product->sale_price_preference_id);
            if ($productPreference) {
                return [
                    'type' => 'product',
                    'preference' => $productPreference,
                    'including_tax' => $product->sale_price_including_tax ?? false
                ];
            }
        }
        // Check global preference
        $globalPreference = Preference::where('type', 'sale_price')
            ->where('status', true)
            ->whereIn('slug', ['static-price', 'stock-wise-price', 'previous-inventory-price'])
            ->first();

        if ($globalPreference) {
            // Check if sale-price-including-tax is enabled globally
            $includingTaxPreference = Preference::where('type', 'sale_price')
                ->where('slug', 'sale-price-including-tax')
                ->where('status', true)
                ->first();

            return [
                'type' => 'global',
                'preference' => $globalPreference,
                'including_tax' => (bool) $includingTaxPreference
            ];
        }

        // Default to static-price
        $defaultPreference = ProductPreference::where('slug', 'static-price')->first();
        return [
            'type' => 'default',
            'preference' => $defaultPreference,
            'including_tax' => false
        ];
    }

    /**
     * Calculate sale price based on preference and selected category
     */
    public function calculateSalePrice($productId, $selectedCategoryId, $preferenceInfo)
    {
        $preference = $preferenceInfo['preference'];
        $includingTax = $preferenceInfo['including_tax'];

        switch ($preference->slug) {
            case 'static-price':
                return $this->getStaticPrice($productId, $selectedCategoryId, $includingTax);

            case 'stock-wise-price':
                return $this->getStockWisePrice($productId, $selectedCategoryId, $includingTax);

            case 'previous-inventory-price':
                return $this->getPreviousInventoryPrice($productId, $selectedCategoryId, $includingTax);

            default:
                return $this->getStaticPrice($productId, $selectedCategoryId, $includingTax);
        }
    }

    /**
     * Get static price from product_parameters
     */
    public function getStaticPrice($productId, $selectedCategoryId, $includingTax)
    {
        $parameter = ProductParameter::where('product_id', $productId)
            ->where('child_category_id', $selectedCategoryId)
            ->first();

        if (!$parameter) {
            return 0;
        }

        // For static-price, we only use static_category_unit_sale_price
        // Tax preference doesn't affect static-price according to requirements
        return $parameter->static_category_unit_sale_price ?? 0;
    }

    /**
     * Get stock-wise price from base_stock_sale_price
     */
    public function getStockWisePrice($productId, $selectedCategoryId, $includingTax)
    {
        // First get base category price from stock with expiry date consideration
        $baseStockPrice = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', Carbon::now())
            ->orderBy('id', 'asc') // FIFO - earliest expiry first
            // ->orderBy('remaining_base_stock', 'asc')
            ->first();

        if (!$baseStockPrice) {
            return 0;
        }


        // Get base price (with or without tax)
        if ($includingTax) {
            $basePrice = $baseStockPrice->base_category_unit_sale_price + ($baseStockPrice->base_category_unit_sale_tax_price ?? 0);
            // If tax price is zero or null, use the regular sale price
            if (!$basePrice || $basePrice == 0) {
                $basePrice = $baseStockPrice->base_category_unit_sale_price;
            }
        } else {
            $basePrice = $baseStockPrice->base_category_unit_sale_price;
        }


        // If selected category is the base category, return base price
        if ($selectedCategoryId == $baseStockPrice->base_category_id) {
            return $basePrice ?? 0;
        }

        // Otherwise, calculate price for selected category using product_parameters quantities
        return $this->calculateCategoryPrice($productId, $selectedCategoryId, $baseStockPrice->base_category_id, $basePrice);
    }

    /**
     * Get previous inventory price from base_stock_sale_price
     */
    public function getPreviousInventoryPrice($productId, $selectedCategoryId, $includingTax)
    {
        // Get the previous entry (not dependent on expiry or remaining stock)
        $baseStockPrice = BaseStockSalePrice::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$baseStockPrice) {
            return 0;
        }

        // Get base price (with or without tax)
        if ($includingTax) {
            // Return the SUM of base price + tax price when including tax
            $basePrice = $baseStockPrice->base_category_unit_sale_price +
                ($baseStockPrice->base_category_unit_sale_tax_price ?? 0);
        } else {
            $basePrice = $baseStockPrice->base_category_unit_sale_price;
        }

        // If selected category is the base category, return base price
        if ($selectedCategoryId == $baseStockPrice->base_category_id) {
            return $basePrice ?? 0;
        }
        // Otherwise, calculate price for selected category using product_parameters quantities
        return $this->calculateCategoryPrice($productId, $selectedCategoryId, $baseStockPrice->base_category_id, $basePrice);
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

    public function getBaseCategoryId($productId)
    {
        $parameters = ProductParameter::where('product_id', $productId)->get();

        // Find all child categories that appear as parents
        $parentCategories = $parameters->pluck('parent_category_id')->unique()->filter()->toArray();

        // Find child categories that are NOT parents (these are the base categories)
        $baseCategories = $parameters->pluck('child_category_id')->unique()->filter()->toArray();
        $baseCategories = array_diff($baseCategories, $parentCategories);

        return !empty($baseCategories) ? reset($baseCategories) : null;
    }

    /**
     * Modified search method with preference-based pricing
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        $products = Product::where('barcode', $query)
            ->orWhere('product_name', 'like', "%{$query}%")
            ->with([
                'parameters.childCategory:id,name',
                'strength'
            ])
            ->orderByRaw("CASE WHEN barcode = ? THEN 0 ELSE 1 END", [$query])
            ->limit(20)
            ->get();

        if ($products->isEmpty()) {
            return response()->json([], 404);
        }

        // Get current cart for stock calculation
        $cart = $request->session()->get('pos_cart', []);

        $responseData = $products->map(function ($product) use ($cart) {
            // Get cart items for this product
            $cartItemsForProduct = array_filter($cart, function ($item) use ($product) {
                return $item['product_id'] == $product->id;
            });

            // Calculate total reserved base quantity
            $totalReservedBaseQty = array_sum(array_column($cartItemsForProduct, 'base_qty'));

            // Check if any stock is available after cart reservation
            $totalAvailableBase = BaseStockSalePrice::where('product_id', $product->id)
                ->where('remaining_base_stock', '>', 0)
                ->where('expiry_date', '>=', now())
                ->sum('remaining_base_stock');

            $actuallyAvailable = max(0, $totalAvailableBase - $totalReservedBaseQty);

            if ($actuallyAvailable <= 0) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'strength' => $product->strength,
                    'price' => 0,
                    'out_of_stock' => true,
                    'message' => 'No stock available (considering cart items)',
                    'default_category_id' => null,
                    'categories' => [],
                    'discount' => $product->discount ?? 0,
                    'discount_percent' => $product->discount_percent ?? 0,
                    'lock_max_discount' => (bool) $product->lock_max_discount,
                ];
            }

            // Original logic continues...
            $preferenceInfo = $this->getSalePricePreference($product->id);

            $defaultCategoryId = $this->getBaseCategoryId($product->id);
            if (!$defaultCategoryId) {
                $latestParam = $product->parameters()->latest()->first();
                $defaultCategoryId = $latestParam->child_category_id ?? null;
            }

            $defaultPrice = $defaultCategoryId
                ? $this->calculateSalePrice($product->id, $defaultCategoryId, $preferenceInfo)
                : 0;

            $categories = $product->parameters->map(function ($param) use ($product, $preferenceInfo) {
                if (!$param->childCategory) return null;

                $categoryPrice = $this->calculateSalePrice($product->id, $param->child_category_id, $preferenceInfo);

                return [
                    'id' => $param->child_category_id,
                    'name' => $param->childCategory->name,
                    'price' => $categoryPrice,
                ];
            })->filter()->values();

            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'strength' => $product->strength,
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
            // Get current cart items for this product (excluding the one being edited)
            $cart = $request->session()->get('pos_cart', []);
            $cartProductItems = array_filter($cart, function ($item) use ($productId, $selectedCategoryId) {
                return $item['product_id'] == $productId
                    && $item['category_id'] == $selectedCategoryId
                    && isset($item['unit_price']);   // ensure price group separation
            });

            // Calculate already reserved quantities per price group
            $alreadyReservedPerPrice = $this->calculateAlreadyReservedPerPrice($cartProductItems);

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
                $alreadyReservedPerPrice,
                $fromStockId
            );

            // Check if enough stock is available
            $totalAvailableBase = $availableStock['total_base_qty'];

            if ($baseQuantityRequired > $totalAvailableBase) {
                $availableInSelected = $this->convertFromBaseQuantityOptimized(
                    $productId,
                    $selectedCategoryId,
                    $totalAvailableBase,
                    $parameters
                );

                return response()->json([
                    'status' => 'error',
                    'message' => "Insufficient stock. Available: " . number_format($availableInSelected, 2) .
                        " in selected category. Required: " . number_format($requestedQuantity, 2),
                    'available_quantity' => $availableInSelected,
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

    function getAvailableStockWithPriceGrouping($productId, $selectedCategoryId, $alreadyReservedPerPrice, $fromStockId = null)
    {
        // Get all stock entries (FIFO order)
        $stockQuery = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', now())
            ->orderBy('id', 'asc');

        if ($fromStockId) {
            $stockQuery->where('id', '>=', $fromStockId);
        }

        $stockRows = $stockQuery->get();

        // Group by price (string key: two decimals) and subtract already reserved
        $priceGroups = [];
        $totalBaseQty = 0;

        foreach ($stockRows as $stockRow) {
            // Calculate unit price for this row for the SELECTED category
            $unitPrice = $this->calculateUnitPriceForStockRow($stockRow, $selectedCategoryId);

            // Normalize price key as string with 2 decimals to match frontend .toFixed(2)
            $priceKey = number_format((float)$unitPrice, 2, '.', '');

            // Already reserved for this priceKey (use string keys)
            $alreadyReserved = $alreadyReservedPerPrice[$priceKey] ?? 0;

            $remainingInRow = $stockRow->remaining_base_stock;

            // FIFO subtract reserved from rows sequentially
            if ($alreadyReserved > 0) {
                if ($alreadyReserved >= $remainingInRow) {
                    $alreadyReservedPerPrice[$priceKey] -= $remainingInRow;
                    $availableQty = 0;
                } else {
                    $availableQty = $remainingInRow - $alreadyReserved;
                    $alreadyReservedPerPrice[$priceKey] = 0;
                }
            } else {
                $availableQty = $remainingInRow;
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

    function calculateAlreadyReservedPerPrice($cartItems)
    {
        $reservedPerPrice = [];

        foreach ($cartItems as $item) {
            // Accept both 'unit_price' or 'price' keys in session payload to be robust
            $unitPrice = null;
            if (isset($item['unit_price'])) {
                $unitPrice = $item['unit_price'];
            } elseif (isset($item['price'])) {
                $unitPrice = $item['price'];
            } elseif (isset($item['price_group'])) {
                $unitPrice = $item['price_group'];
            }

            if ($unitPrice !== null && isset($item['base_qty'])) {
                // normalize key to 2-decimal string (same format used elsewhere)
                $priceKey = number_format((float)$unitPrice, 2, '.', '');
                $reservedPerPrice[$priceKey] = ($reservedPerPrice[$priceKey] ?? 0) + (float)$item['base_qty'];
            }
        }

        return $reservedPerPrice;
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
        // Get preference
        $preferenceInfo = $this->getSalePricePreference($stockRow->product_id);
        $includingTax = $preferenceInfo['including_tax'];

        // Calculate base price
        if ($includingTax) {
            $basePrice = $stockRow->base_category_unit_sale_price +
                ($stockRow->base_category_unit_sale_tax_price ?? 0);
        } else {
            $basePrice = $stockRow->base_category_unit_sale_price;
        }

        // If selected category is base category
        if ($selectedCategoryId == $stockRow->base_category_id) {
            return round($basePrice, 2);
        }

        // Convert to selected category price
        return round($this->calculateCategoryPrice(
            $stockRow->product_id,
            $selectedCategoryId,
            $stockRow->base_category_id,
            $basePrice
        ), 2);
    }

    public function saveInvoice(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.base_qty' => 'required|numeric|min:0.01',
            'items.*.base_stock_sale_price_id' => 'required|exists:base_stock_sale_price,id',
            'items.*.category_id' => 'required|exists:categories,id'
        ]);

        try {
            DB::beginTransaction();

            foreach ($request->items as $item) {

                $stockRow = BaseStockSalePrice::lockForUpdate()
                    ->find($item['base_stock_sale_price_id']);

                if (!$stockRow) {
                    throw new \Exception("Stock row not found.");
                }

                if ($stockRow->remaining_base_stock < $item['base_qty']) {
                    throw new \Exception("Insufficient stock in selected batch.");
                }

                $stockRow->remaining_base_stock -= $item['base_qty'];
                $stockRow->save();
            }

            $invoice = $this->createInvoiceRecord($request);

            DB::commit();

            $request->session()->forget('pos_cart');

            return response()->json([
                'status' => 'success',
                'invoice_id' => $invoice->id,
                'message' => 'Invoice saved successfully'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Error saving invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deduct stock by price group (FIFO)
     */
    private function deductStockByPriceGroup($productId, $priceKey, $totalBaseQty)
    {
        $remainingToDeduct = $totalBaseQty;

        // Get stock rows in FIFO order for this product
        $stockRows = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', now())
            ->orderBy('id', 'asc')
            ->get();

        foreach ($stockRows as $stockRow) {
            if ($remainingToDeduct <= 0) break;

            // Calculate unit price for this row to match price group
            $calculatedPrice = $this->calculateUnitPriceForStockRow($stockRow, $stockRow->base_category_id);

            if (round($calculatedPrice, 2) == $priceKey) {
                $deductAmount = min($remainingToDeduct, $stockRow->remaining_base_stock);

                $stockRow->remaining_base_stock -= $deductAmount;
                $stockRow->save();

                $remainingToDeduct -= $deductAmount;
            }
        }

        if ($remainingToDeduct > 0) {
            throw new \Exception("Could not deduct all stock for price group $priceKey");
        }
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

    /**
     * Optimized stock-wise pricing with category consideration
     */
    public function handleStockWisePricingWithCategoryOptimized(
        $productId,
        $baseQuantityRequired,
        $selectedCategoryId,
        $preferenceInfo,
        $requestedQuantity,
        $parameters = null,
        $fromStockId
    ) {
        $stockRows = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', now())
            ->when($fromStockId, function ($q) use ($fromStockId) {
                $q->where('id', '>=', $fromStockId);
            })
            ->orderBy('id', 'asc')
            ->get();

        if ($stockRows->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active stock entries available.'
            ]);
        }

        $rowsForPOS = [];
        $remainingBaseQty = $baseQuantityRequired;

        foreach ($stockRows as $stockRow) {
            if ($remainingBaseQty <= 0) break;

            $usableBaseQty = min($remainingBaseQty, $stockRow->remaining_base_stock);

            // ✅ Convert base to selected category
            $useSelectedQty = $this->convertFromBaseQuantityOptimized(
                $productId,
                $selectedCategoryId,
                $usableBaseQty,
                $parameters
            );

            // ✅ GET PRICE FROM ***THIS*** STOCK ROW ONLY
            if ($preferenceInfo['including_tax']) {
                $unitBasePrice =
                    $stockRow->base_category_unit_sale_price +
                    ($stockRow->base_category_unit_sale_tax_price ?? 0);
            } else {
                $unitBasePrice = $stockRow->base_category_unit_sale_price;
            }

            // ✅ Convert price if parent category selected
            if ($selectedCategoryId != $stockRow->base_category_id) {
                $unitPrice = $this->calculateCategoryPrice(
                    $productId,
                    $selectedCategoryId,
                    $stockRow->base_category_id,
                    $unitBasePrice
                );
            } else {
                $unitPrice = $unitBasePrice;
            }

            $rowsForPOS[] = [
                'product_id' => $productId,
                'quantity' => round($useSelectedQty, 4),
                'unit_price' => round($unitPrice, 4),
                'base_stock_sale_price_id' => $stockRow->id,
            ];

            $remainingBaseQty -= $usableBaseQty;
        }

        if ($remainingBaseQty > 0) {
            $totalAvailable = $stockRows->sum('remaining_base_stock');
            $availableInSelectedCategory = $this->convertFromBaseQuantityOptimized(
                $productId,
                $selectedCategoryId,
                $totalAvailable,
                $parameters
            );

            return response()->json([
                'status' => 'error',
                'message' => "Not enough stock available. Required: $requestedQuantity, Available: " .
                    number_format($availableInSelectedCategory, 2)
            ]);
        }

        return response()->json([
            'status' => 'ok',
            'rows' => $rowsForPOS
        ]);
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
}
