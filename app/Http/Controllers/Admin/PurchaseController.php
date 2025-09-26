<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Tax;
use App\Models\ProductParameter;
use App\Models\PurchaseStock;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $title = 'purchases';
        if ($request->ajax()) {
            $purchases = Purchase::get();
            return DataTables::of($purchases)
                ->addColumn('product', function ($purchase) {
                    $image = '';
                    if (!empty($purchase->image)) {
                        $image = '<span class="avatar avatar-sm mr-2">
						<img class="avatar-img" src="' . asset("storage/purchases/" . $purchase->image) . '" alt="product">
					    </span>';
                    }
                    return $purchase->product->product_name . ' ' . $image;
                })
                ->addColumn('category', function ($purchase) {
                    if (!empty($purchase->category)) {
                        return $purchase->category->name;
                    }
                })
                ->addColumn('unit_cost_price', function ($purchase) {
                    return settings('app_currency', 'Rs') . ' ' . $purchase->unit_cost_price;
                })
                ->addColumn('supplier', function ($purchase) {
                    return $purchase->supplier->name;
                })
                ->addColumn('expiry_date', function ($purchase) {
                    return date_format(date_create($purchase->expiry_date), 'd M, Y');
                })
                ->addColumn('action', function ($row) {
                    $editbtn = '<a href="' . route("purchases.edit", $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('purchases.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';
                    $btn = $editbtn . ' ' . $deletebtn;
                    return $btn;
                })
                ->rawColumns(['product', 'action'])
                ->make(true);
        }
        return view('admin.purchases.index', compact(
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
        $title = 'create purchase';
        $categories = Category::get();
        $suppliers = Supplier::get();
        $products = Product::get();
        $taxes = Tax::get();
        return view('admin.purchases.create', compact(
            'title',
            'categories',
            'suppliers',
            'products',
            'taxes'
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
            'product' => 'required|max:200',
            'category' => 'required',
            'unit_cost_price' => 'required|min:1',
            'quantity' => 'required|min:1',
            'expiry_date' => 'required',
            'supplier' => 'required',
            'image' => 'file|image|mimes:jpg,jpeg,png,gif',
        ]);

        $imageName = null;
        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('storage/purchases'), $imageName);
        }

        // Find base category and calculate stock
        $productId   = $request->product;
        $categoryId  = $request->category;
        $quantity    = $request->quantity;

        [$baseCategoryId, $baseQty] = $this->calculateBaseStock($productId, $categoryId, $quantity);

        $purchase = Purchase::create([
            'product_id' => $request->product,
            'category_id' => $request->category,
            'supplier_id' => $request->supplier,
            'unit_cost_price' => $request->unit_cost_price,
            'total_cost_price' => $request->unit_cost_price * $request->quantity,
            'unit_cost_tax_amount' => $request->unit_cost_tax_amount,
            'total_cost_tax_amount' => $request->total_cost_tax_amount,
            'batch_no' => $request->batch_no,
            'quantity' => $request->quantity,
            'base_category_id' => $baseCategoryId,
            'base_quantity' => $baseQty,
            'expiry_date' => $request->expiry_date,
            'image' => $imageName,
            'unit_sale_price'        => $request->unit_sale_price,
            'total_sale_price'       => $request->unit_sale_price * $request->quantity,
            'unit_sale_tax_amount'   => $request->unit_sale_tax_amount,
            'total_sale_tax_amount'  => $request->total_sale_tax_amount,
        ]);

        // Save taxes
        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                \App\Models\PurchaseTax::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $request->product,
                    'tax_id'      => $tax['id'],
                    'tax_rate'    => $tax['rate'],
                    'tax_unit_amount'    => $tax['unit'],
                    'tax_amount'  => $tax['total'],
                ]);
            }
        }

        if ($request->has('sale_taxes')) {
            foreach ($request->sale_taxes as $tax) {
                \App\Models\SaleTax::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $request->product,
                    'tax_id'      => $tax['id'],
                    'tax_rate'    => $tax['rate'],
                    'tax_unit_amount' => $tax['unit'],
                    'tax_amount'  => $tax['total'],
                ]);
            }
        }

        $stock = PurchaseStock::where('product_id', $productId)->first();

        if ($stock) {
            $stock->increment('current_stock', $baseQty);
            $stock->base_category_id = $baseCategoryId;
            $stock->purchase_id = $purchase->id;
            $stock->save();
        } else {
            PurchaseStock::create([
                'purchase_id'      => $purchase->id,
                'product_id'       => $productId,
                'base_category_id' => $baseCategoryId,
                'current_stock'    => $baseQty,
            ]);
        }

        $notifications = notify("Purchase has been added");
        return redirect()->route('purchases.index')->with($notifications);
    }

    private function calculateBaseStock($productId, $selectedCategoryId, $quantity)
    {
        $params = ProductParameter::where('product_id', $productId)->get();

        // Build lookup: parent → [child => qty]
        $map = [];
        foreach ($params as $p) {
            $map[$p->parent_category_id][$p->child_category_id] = $p->quantity;
        }

        $currentCategory = $selectedCategoryId;
        $currentQty      = $quantity;

        // Traverse until no child exists
        while (isset($map[$currentCategory])) {
            // Always pick the child in the chain
            $childId   = array_key_first($map[$currentCategory]);
            $multiplier = $map[$currentCategory][$childId];
            $currentQty = $currentQty * $multiplier;
            $currentCategory = $childId;
        }

        // $currentCategory is base category
        return [$currentCategory, $currentQty];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \app\Models\Purchase $purchase
     * @return \Illuminate\Http\Response
     */
    public function edit(Purchase $purchase)
    {
        $title      = 'Edit Purchase';
        $categories = Category::all();
        $suppliers  = Supplier::all();
        $products   = Product::all();
        $taxes      = Tax::all();

        // eager load purchase taxes + their tax info
        $purchase->load(['taxes.tax']);

        return view('admin.purchases.edit', compact(
            'title',
            'purchase',
            'categories',
            'suppliers',
            'products',
            'taxes'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Purchase $purchase
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Purchase $purchase)
    {
        $this->validate($request, [
            'product'          => 'required',
            'category'         => 'required',
            'supplier'         => 'required',
            'unit_cost_price'  => 'required|min:1',
            'quantity'         => 'required|min:1',
            'expiry_date'      => 'required|date',
            'batch_no'         => 'required',
            'image'            => 'file|image|mimes:jpg,jpeg,png,gif',
        ]);

        $imageName = $purchase->image;
        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('storage/purchases'), $imageName);
        }

        [$baseCategoryId, $baseQty] = $this->calculateBaseStock($request->product, $request->category, $request->quantity);
        $oldQty = $purchase->base_quantity;
        $purchase->update([
            'product_id'             => $request->product,
            'category_id'            => $request->category,
            'supplier_id'            => $request->supplier,
            'unit_cost_price'        => $request->unit_cost_price,
            'total_cost_price'       => $request->unit_cost_price * $request->quantity,
            'unit_cost_tax_amount'   => $request->unit_cost_tax_amount,
            'total_cost_tax_amount'  => $request->total_cost_tax_amount,
            'batch_no'               => $request->batch_no,
            'quantity'               => $request->quantity,
            'base_category_id'       => $baseCategoryId,
            'base_quantity'          => $baseQty,
            'expiry_date'            => $request->expiry_date,
            'image'                  => $imageName,
            'unit_sale_price'        => $request->unit_sale_price,
            'total_sale_price'       => $request->unit_sale_price * $request->quantity,
            'unit_sale_tax_amount'   => $request->unit_sale_tax_amount,
            'total_sale_tax_amount'  => $request->total_sale_tax_amount,
        ]);

        // sync taxes
        $purchase->taxes()->delete(); // clear old
        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                \App\Models\PurchaseTax::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $request->product,
                    'tax_id'      => $tax['id'],
                    'tax_rate'    => $tax['rate'],
                    'tax_unit_amount'    => $tax['unit'],
                    'tax_amount'  => $tax['total'],
                ]);
            }
        }
        $purchase->Saletaxes()->delete(); // clear old
        if ($request->has('sale_taxes')) {
            foreach ($request->sale_taxes as $tax) {
                \App\Models\SaleTax::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $request->product,
                    'tax_id'      => $tax['id'],
                    'tax_rate'    => $tax['rate'],
                    'tax_unit_amount' => $tax['unit'],
                    'tax_amount'  => $tax['total'],
                ]);
            }
        }

        $stock = PurchaseStock::where('product_id', $request->product)->first();
        if ($stock) {
            $difference = $baseQty - $oldQty;
            $stock->increment('current_stock', $difference);
            $stock->save();
        }

        $notifications = notify("Purchase has been updated");
        return redirect()->route('purchases.index')->with($notifications);
    }

    public function reports()
    {
        $title = 'purchase reports';
        return view('admin.purchases.reports', compact('title'));
    }

    public function generateReport(Request $request)
    {
        $this->validate($request, [
            'from_date' => 'required',
            'to_date' => 'required'
        ]);
        $title = 'purchases reports';
        $purchases = Purchase::whereBetween(DB::raw('DATE(created_at)'), array($request->from_date, $request->to_date))->get();
        return view('admin.purchases.reports', compact(
            'purchases',
            'title'
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
        return Purchase::findOrFail($request->id)->delete();
    }
}
