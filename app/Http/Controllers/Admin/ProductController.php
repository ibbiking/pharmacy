<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Category;
use App\Models\ProductParameter;
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
                    $editbtn = '<a href="' . route("products.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('products.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    $paramBtn = '<a href="' . route("products.parameters", $row->id) . '" class="btn btn-warning"><i class="fas fa-sliders-h"></i></button></a>';
                    if (!auth()->user()->hasPermissionTo('edit-product')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-purchase')) {
                        $deletebtn = '';
                    }
                    $btn = $editbtn . ' ' . $deletebtn . ' ' . $paramBtn;
                    return $btn;
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
        $purchases = Purchase::get();
        return view('admin.products.create', compact(
            'title',
            'purchases'
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
            'product_name' => 'required|max:200',
            'description' => 'nullable|max:255',
        ]);
        Product::create([
            'product_name' => $request->product_name,
            'description' => $request->description,
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
        $purchases = Purchase::get();
        return view('admin.products.edit', compact(
            'title',
            'product',
            'purchases'
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
            'product' => 'required|max:200',
            'price' => 'required',
            'discount' => 'nullable',
            'description' => 'nullable|max:255',
        ]);

        $price = $request->price;
        if ($request->discount > 0) {
            $price = $request->discount * $request->price;
        }
        $product->update([
            'purchase_id' => $request->product,
            'price' => $price,
            'discount' => $request->discount,
            'description' => $request->description,
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
                    if (!auth()->user()->hasPermissionTo('edit-product')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-purchase')) {
                        $deletebtn = '';
                    }
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
                    if (!auth()->user()->hasPermissionTo('edit-product')) {
                        $editbtn = '';
                    }
                    if (!auth()->user()->hasPermissionTo('destroy-purchase')) {
                        $deletebtn = '';
                    }
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
        $categories = Category::with('childrenRecursive')->get();

        // fetch product parameters with categories
        $parameters = $product->parameters()
            ->with(['parentCategory', 'childCategory'])
            ->get();

        return view('admin.products.parameters', compact('title', 'product', 'categories', 'parameters'));
    }

    public function storeParameters(Request $request, $productId)
{
    foreach ($request->parameters as $param) {
        $record = ProductParameter::where('product_id', $productId)
            ->where('parent_category_id', $param['parent_category_id'] ?: null)
            ->where('child_category_id', $param['child_category_id'])
            ->first();

        if ($record) {
            $record->update([
                'quantity' => $param['quantity']
            ]);
        } else {
            ProductParameter::create([
                'product_id' => $productId,
                'category_id' => $param['category_id'],
                'parent_category_id' => $param['parent_category_id'] ?: null,
                'child_category_id' => $param['child_category_id'],
                'quantity' => $param['quantity'],
            ]);
        }
    }

    return redirect()->back()->with('success', 'Packaging parameters saved successfully.');
}
}
