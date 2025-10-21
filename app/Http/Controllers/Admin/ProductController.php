<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Category;
use App\Models\Company;
use App\Models\Farmula;
use App\Models\ProductParameter;
use App\Models\PurchaseStock;
use App\Models\ProductPreference;
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
            'sale_price_preference_id'  => $defaultPreference->id ?? null, // set default
            'barcode'                   => $request->barcode,
            'discount'                  => $request->discount ?? 0,
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
        $stock = PurchaseStock::where('product_id', $productId)->sum('current_stock');
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
            'sale_price_preference_id' => 'required|exists:preferences,id',
        ]);

        $product->update([
            'sale_price_preference_id' => $request->sale_price_preference_id,
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

    public function search(Request $request)
    {
        $query = $request->get('q');
        $product = Product::where('barcode', $query)
            ->orWhere('product_name', 'like', "%{$query}%")
            ->with([
                'parameters.childCategory:id,name',
            ])
            ->first();

        if (!$product) {
            return response()->json([], 404);
        }

        // latest parameter with category info
        $latestParam = $product->parameters()->latest()->first();

        return response()->json([
            'id' => $product->id,
            'product_name' => $product->product_name,
            'strength' => $product->strength,
            'price' => $latestParam->static_category_unit_sale_price ?? 0,
            'default_category_id' => $latestParam->child_category_id ?? null,
            'categories' => $product->parameters->pluck('childCategory')->filter()->unique('id')->values()->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                ];
            }),
        ]);
    }
}
