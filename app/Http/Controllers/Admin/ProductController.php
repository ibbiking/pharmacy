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
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $title = 'products';
        if ($request->ajax()) {
            $products = Product::with(['company', 'type'])->real()->latest();
            return DataTables::of($products)
                ->filterColumn('company', function ($query, $keyword) {
                    $query->whereHas('company', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('type', function ($query, $keyword) {
                    $query->whereHas('type', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('strength', function ($query, $keyword) {
                    $strengthIds = \App\Models\Strength::where('name', 'like', "%{$keyword}%")->pluck('id');
                    if ($strengthIds->count()) {
                        $query->where(function ($q) use ($strengthIds) {
                            foreach ($strengthIds as $id) {
                                $q->orWhere('strength_id', 'like', $id . ',%')
                                  ->orWhere('strength_id', 'like', '%,' . $id . ',%')
                                  ->orWhere('strength_id', 'like', '%,' . $id)
                                  ->orWhere('strength_id', (string)$id);
                            }
                        });
                    } else {
                        $query->whereRaw("1 = 0");
                    }
                })
                ->filterColumn('farmula', function ($query, $keyword) {
                    $farmulaIds = \App\Models\Farmula::where('name', 'like', "%{$keyword}%")->pluck('id');
                    if ($farmulaIds->count()) {
                        $query->where(function ($q) use ($farmulaIds) {
                            foreach ($farmulaIds as $id) {
                                $q->orWhere('farmula_id', 'like', $id . ',%')
                                  ->orWhere('farmula_id', 'like', '%,' . $id . ',%')
                                  ->orWhere('farmula_id', 'like', '%,' . $id)
                                  ->orWhere('farmula_id', (string)$id);
                            }
                        });
                    } else {
                        $query->whereRaw("1 = 0");
                    }
                })
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->where('product_name', 'like', "%{$keyword}%");
                })
                ->editColumn('product_name', function ($product) {
                    $image = '';
                    $image = null;
                    if (!empty($product->image)) {
                        $image = '<span class="avatar avatar-sm mr-2">
                            <img class="avatar-img" src="' . asset("storage/purchases/" . $product->image) . '" alt="image">
                            </span>';
                    }
                    return $product->product_name . ' ' . $image;
                })->addColumn('strength', function ($product) {
                    if (!$product->strength_id) return '-';
                    $ids = explode(',', $product->strength_id);
                    $names = \App\Models\Strength::whereIn('id', $ids)->pluck('name');
                    $spans = '';
                    foreach ($names as $name) {
                        $spans .= '<span class="badge badge-info mr-1">' . $name . '</span>';
                    }
                    return $spans ?: '-';
                })->addColumn('type', function ($product) {
                    return $product->type->name ?? '-';
                })->addColumn('company', function ($product) {
                    return $product->company->name ?? '-';
                })
                ->addColumn('farmula', function ($product) {
                    if (!$product->farmula_id) return '-';
                    $ids = explode(',', $product->farmula_id);
                    $names = \App\Models\Farmula::whereIn('id', $ids)->pluck('name');
                    $spans = '';
                    foreach ($names as $name) {
                        $spans .= '<span class="badge badge-info mr-1">' . $name . '</span>';
                    }
                    return $spans ?: '-';
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

                    $setupBtn = '<button type="button" class="btn btn-primary btn-setup-wizard ml-1" data-id="' . $row->id . '" title="Setup Configuration">
                        <i class="fas fa-cogs"></i>
                    </button>';

                    $stockBtn = '<button class="btn btn-secondary show-stock ml-1" 
        data-id="' . $row->id . '" 
        title="View Stock Summary">
        <i class="fas fa-box"></i>
    </button>';

                    $priceBtn = '<button class="btn btn-warning show-price-summary ml-1" 
        data-id="' . $row->id . '" 
        title="Price Summary">
        <i class="fas fa-dollar-sign"></i>
    </button>';

                    $addStockBtn = '<button class="btn btn-success btn-quick-stock ml-1" 
        data-id="' . $row->id . '" 
        title="Quick Add Stock">
        <i class="fas fa-plus"></i>
    </button>';

                    return $editbtn . ' ' . $deletebtn . ' ' . $setupBtn . ' ' . $stockBtn . ' ' . $priceBtn . ' ' . $addStockBtn;
                })
                ->rawColumns(['product_name', 'action', 'farmula', 'strength'])
                ->make(true);
        }
        $companies   = Company::all();
        $farmulas    = Farmula::all();
        $types = \App\Models\ProductType::all();
        $strengths    = \App\Models\Strength::all();
        return view('admin.products.index', compact(
            'title', 'companies', 'farmulas', 'types', 'strengths'
        ));
    }

    public function drafts(Request $request)
    {
        $title = 'drafts';
        if ($request->ajax()) {
            $products = Product::with(['company'])->draft()->latest();
            return DataTables::of($products)
                ->filterColumn('company', function ($query, $keyword) {
                    $query->whereHas('company', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->where('product_name', 'like', "%{$keyword}%");
                })
                ->editColumn('product_name', function ($product) {
                    return $product->product_name;
                })->addColumn('company', function ($product) {
                    return $product->company->name ?? '-';
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("products.edit", $row->id) . '" class="editbtn">
                        <button class="btn btn-info" title="Edit Product"><i class="fas fa-edit"></i></button>
                    </a>';

                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('products.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn">
                        <button class="btn btn-danger" title="Delete Product"><i class="fas fa-trash"></i></button>
                    </a>';

                    $setupBtn = '<button type="button" class="btn btn-primary btn-setup-wizard ml-1" data-id="' . $row->id . '" title="Complete Setup">
                        <i class="fas fa-cogs"></i> Complete Setup
                    </button>';

                    return $editbtn . ' ' . $deletebtn . ' ' . $setupBtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.products.drafts', compact('title'));
    }

    public function setupWizard(Product $product)
    {
        // 1. Categories
        $relations = \App\Models\ProductCategory::with(['parentCategory', 'childCategory'])
            ->where('product_id', $product->id)
            ->get();

        $productCategory = $relations->first();
        $parentCategoryId = $productCategory ? $productCategory->parent_category_id : null;
        $childCategoryId  = $productCategory ? $productCategory->child_category_id : null;

        $lastChildId = null;

        if ($relations->count()) {
            $map = [];
            foreach ($relations as $r) {
                $map[(int)$r->parent_category_id] = (int)$r->child_category_id;
            }
            $parents = array_keys($map);
            $children = array_values($map);
            $start = null;
            foreach ($parents as $p) {
                if (! in_array($p, $children, true)) {
                    $start = $p;
                    break;
                }
            }
            if ($start === null) {
                $start = (int)$relations->first()->parent_category_id;
            }
            $current = $start;
            while (isset($map[$current])) {
                if ($map[$current] == $current) break; // Fix: prevent infinite loop if parent == child
                $current = $map[$current];
            }
            $lastChildId = $current;
        }

        $parentCategories = $lastChildId ? Category::where('id', $lastChildId)->get() : Category::all();

        $usedParentIds = $relations->pluck('parent_category_id')->filter()->toArray();
        $usedChildIds  = $relations->pluck('child_category_id')->filter()->toArray();
        $exclude = array_values(array_filter(array_unique(array_merge($usedParentIds, $usedChildIds))));
        $childCategories = Category::when(count($exclude), function ($q) use ($exclude) {
            $q->whereNotIn('id', $exclude);
        })->get();

        // 2. Parameters
        $baseCategory = $productCategory ? $productCategory->parentCategory : null;
        while ($baseCategory && $baseCategory->parentCategory) {
            $baseCategory = $baseCategory->parentCategory;
        }

        $children = $relations->map(function($r) {
            if ($r->parent_category_id == $r->child_category_id) {
                return null;
            }
            if ($r->childCategory) {
                $r->childCategory->parent_id = $r->parent_category_id;
                $r->childCategory->setRelation('parent', $r->parentCategory);
                return $r->childCategory;
            }
            return null;
        })->filter();
        
        $parameters = $product->parameters()->with(['parentCategory', 'childCategory'])->get()->keyBy('child_category_id');

        // 3. Preferences
        $availablePreferences = \App\Models\ProductPreference::where('type', 'sale_price')->get();

        return view('admin.products.wizard_slider', compact(
            'product', 'productCategory', 'parentCategoryId', 'childCategoryId',
            'relations', 'parentCategories', 'childCategories', 'lastChildId',
            'baseCategory', 'children', 'parameters',
            'availablePreferences'
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
            'product_name'     => [
                'required',
                'max:200',
                \Illuminate\Validation\Rule::unique('products')->where(function ($query) use ($request) {
                    $strengthStr = $request->strength_id ? implode(',', $request->strength_id) : null;
                    return $query->where('strength_id', $strengthStr)->where('product_type_id', $request->product_type_id);
                })->whereNull('deleted_at')
            ],
            'description'      => 'nullable|max:255',
            'company_id'       => 'required|exists:companies,id',
            'farmula_id'       => 'required|array',
            'farmula_id.*'     => 'exists:farmulas,id',
            'product_type_id'  => 'required|exists:product_types,id',
            'strength_id'      => 'nullable|array',
            'strength_id.*'    => 'exists:strengths,id',
            'barcode'          => 'nullable|max:100',
            'discount'         => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack'          => 'nullable',
        ]);

        // get default preference by slug
        $defaultPreference = ProductPreference::where('slug', 'static-price')->first();

        $product = Product::create([
            'product_name'              => $request->product_name,
            'description'               => $request->description,
            'company_id'                => $request->company_id,
            'farmula_id'                => implode(',', $request->farmula_id),
            'product_type_id'           => $request->product_type_id,
            'strength_id'               => $request->strength_id ? implode(',', $request->strength_id) : null,
            'sale_price_preference_id'  =>  null, // $defaultPreference->id ?? null, // set default
            'barcode'                   => $request->barcode,
            'discount'                  => $request->discount ?? 0,
            'discount_percent'  => $request->discount_percent ?? 0,
            'lock_max_discount'         => $request->has('lock_max_discount'),
            'rack'                   => $request->rack,
        ]);

        $notification = notify("Product added as Draft. Click 'Complete Setup' to configure parameters and activate it.");
        return redirect()->route('products.drafts')->with($notification)->with('auto_open_wizard', $product->id);
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
        $product->load(['company', 'type', 'strength']);

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
            'product_name'     => [
                'required',
                'max:200',
                \Illuminate\Validation\Rule::unique('products')->where(function ($query) use ($request) {
                    $strengthStr = $request->strength_id ? implode(',', $request->strength_id) : null;
                    return $query->where('strength_id', $strengthStr)->where('product_type_id', $request->product_type_id);
                })->whereNull('deleted_at')->ignore($product->id)
            ],
            'description'      => 'nullable|max:255',
            'company_id'       => 'required|exists:companies,id',
            'farmula_id'       => 'required|array',
            'farmula_id.*'     => 'exists:farmulas,id',
            'product_type_id'  => 'required|exists:product_types,id',
            'strength_id'      => 'nullable|array',
            'strength_id.*'    => 'exists:strengths,id',
            'barcode'          => 'nullable|max:100',
            'discount'         => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'rack'          => 'nullable',
        ]);

        $product->update([
            'product_name'    => $request->product_name,
            'description'     => $request->description,
            'company_id'      => $request->company_id,
            'farmula_id'      => implode(',', $request->farmula_id),
            'product_type_id' => $request->product_type_id,
            'strength_id'     => $request->strength_id ? implode(',', $request->strength_id) : null,
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
            $products = Purchase::with(['product.category'])->whereDate('expiry_date', '=', Carbon::now());
            return DataTables::of($products)
                ->filterColumn('product', function($query, $keyword) {
                    $query->whereHas('product', function($q) use($keyword) {
                        $q->where('product_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('category', function($query, $keyword) {
                    $query->whereHas('product.category', function($q) use($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('expiry_date', function($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(expiry_date, '%d M, %Y') like ?", ["%$keyword%"]);
                })
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
            $products = Purchase::with(['product.category'])->where('quantity', '<=', 0);
            return DataTables::of($products)
                ->filterColumn('product', function($query, $keyword) {
                    $query->whereHas('product', function($q) use($keyword) {
                        $q->where('product_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('category', function($query, $keyword) {
                    $query->whereHas('product.category', function($q) use($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('expiry_date', function($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(expiry_date, '%d M, %Y') like ?", ["%$keyword%"]);
                })
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
                    $currency = settings('app_currency');
                    return ($currency ? $currency : '$') . ' ' . $product->price;
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
        // Validation check for Purchase < Sale price
        foreach ($request->parameters as $param) {
            $purchasePrice = (float)($param['static_category_unit_purchase_price'] ?? 0);
            $salePrice = (float)($param['static_category_unit_sale_price'] ?? 0);
            
            if ($purchasePrice > 0 && $salePrice > 0 && $purchasePrice >= $salePrice) {
                return redirect()->back()->with('error', 'Validation Failed: Static purchase price must be strictly lower than static sale price.');
            }
        }

        foreach ($request->parameters as $param) {
            $parentCategoryId = $param['parent_category_id'] ?? 0; // default 0 if not set

            $record = ProductParameter::where('product_id', $productId)
                ->where('parent_category_id', $parentCategoryId)
                ->where('child_category_id', $param['child_category_id'])
                ->first();

            $data = [
                'quantity' => $param['quantity'] ?? 1, // parent = 1 by default
                'static_category_unit_sale_price' => $param['static_category_unit_sale_price'] ?? null,
                'static_category_unit_purchase_price' => $param['static_category_unit_purchase_price'] ?? null,
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
                    'static_category_unit_purchase_price' => $param['static_category_unit_purchase_price'] ?? null,
                ]);
            }
        }
        
        // Setup is complete! Promote product to real.
        $product = Product::find($productId);
        if ($product && $product->is_draft) {
            $product->update(['is_draft' => false]);
            // You can optionally return a special JSON response here if handling via AJAX Wizard
            if ($request->ajax()) {
                return response()->json(['success' => true, 'promoted' => true, 'message' => 'Product Configuration Complete! Promoted to Real Product.']);
            }
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Packaging parameters saved successfully.']);
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

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Sale price preference updated successfully.']);
        }

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
     * Modified search method with preference-based pricing
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

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
                    $formulaNames = \App\Models\Farmula::whereIn('id', $ids)->pluck('name')->implode(', ');
                }

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'strength' => $product->strength,
                    'farmula' => $formulaNames,
                    'price' => 0,
                    'out_of_stock' => true,
                    'message' => 'No stock available (considering cart items)',
                    'default_category_id' => $categories->last()['id'] ?? null,
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
                 $smartCategoryId = $categories->last()['id'] ?? null;
            }

            $defaultCategoryId = $smartCategoryId;

            $defaultPrice = $defaultCategoryId
                ? $categories->where('id', $defaultCategoryId)->first()['price'] ?? 0
                : 0;

            $formulaNames = '';
            if ($product->farmula_id) {
                $ids = explode(',', $product->farmula_id);
                $formulaNames = \App\Models\Farmula::whereIn('id', $ids)->pluck('name')->implode(', ');
            }

            return [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'strength' => $product->strength,
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
            $alreadyReserved = $this->calculateAlreadyReserved($cartProductItems);

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
                $fromStockId
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

    function getAvailableStockWithPriceGrouping($productId, $selectedCategoryId, $alreadyReserved, $fromStockId = null)
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

    function calculateAlreadyReserved($cartItems)
    {
        $reserved = [
            'per_batch' => [],
            'per_price' => []
        ];

        foreach ($cartItems as $item) {
            if (!isset($item['base_qty'])) continue;

            $baseQty = (float)$item['base_qty'];

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

    public function quickStockModal($id)
    {
        $product = Product::findOrFail($id);
        $suppliers = \App\Models\Supplier::all();
        $taxes = \App\Models\Tax::all();
        
        return view('admin.products.quick_stock_modal', compact('product', 'suppliers', 'taxes'));
    }
}
