<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Tax;
use App\Models\ProductParameter;
use App\Models\ProductStock;
use App\Models\StockPrices;
use App\Models\BaseStockSalePrice;
use App\Models\PurchaseTax;
use App\Models\SaleTax;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;
use Illuminate\Support\Carbon;

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
            $purchases = Purchase::with(['product', 'category', 'supplier']);
            return DataTables::of($purchases)
                ->addColumn('product', function ($purchase) {
                    $image = '';
                    if (!empty($purchase->image)) {
                        $image = '<span class="avatar avatar-sm mr-2">
						<img class="avatar-img" src="' . asset("storage/purchases/" . $purchase->image) . '" alt="product">
					    </span>';
                    }
                    $productName = $purchase->product->product_name ?? 'Unknown Product';
                    return $productName . ' ' . $image;
                })
                ->addColumn('category', function ($purchase) {
                    return $purchase->category->name ?? 'Unknown Category';
                })
                ->addColumn('unit_cost_price', function ($purchase) {
                    return settings('app_currency', 'Rs') . ' ' . $purchase->unit_cost_price;
                })
                ->addColumn('paid_unit_cost_price', function ($purchase) {
                    return settings('app_currency', 'Rs') . ' ' . ($purchase->paid_unit_cost_price ?? $purchase->unit_cost_price);
                })
                ->addColumn('supplier', function ($purchase) {
                    return $purchase->supplier->name ?? 'Unknown Supplier';
                })
                ->addColumn('expiry_date', function ($purchase) {
                    return date_format(date_create($purchase->expiry_date), 'd M, Y');
                })
                ->addColumn('invoice_no', function ($purchase) {
                    return $purchase->invoice_no ?? '-';
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
            'paid_unit_cost_price' => 'nullable|numeric|min:0',
            'extra_paid_per_unit'  => 'nullable|numeric|min:0',
            'extra_paid_percent'   => 'nullable|numeric|min:0|max:100',
            'invoice_no'           => 'nullable|string|max:255',
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

        $total_cost_price = $request->unit_cost_price * $request->quantity;
        $total_cost_tax_price = $request->total_cost_tax_amount;
        $total_sale_price = $request->unit_sale_price * $request->quantity;
        $total_sale_tax_price = $request->total_sale_tax_amount;
        $extraPaidPerUnit  = $request->extra_paid_per_unit ?? 0;
        $extraPaidPercent = $request->extra_paid_percent ?? 0;

        $paidUnitCost = $request->unit_cost_price + $extraPaidPerUnit;

        $paidExtraTotalCost = $paidUnitCost * $request->quantity;

        $purchase = Purchase::create([
            'product_id' => $request->product,
            'category_id' => $request->category,
            'supplier_id' => $request->supplier,
            'unit_cost_price' => $request->unit_cost_price,
            'total_cost_price' => $request->unit_cost_price * $request->quantity,
            'unit_cost_tax_amount' => $request->unit_cost_tax_amount,
            'total_cost_tax_amount' => $request->total_cost_tax_amount,
            'invoice_no' => $request->invoice_no,
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
            'base_unit_purchase_price'  => round($total_cost_price / $baseQty, 6),
            'base_unit_purchase_tax_price' => round($total_cost_tax_price / $baseQty, 6),
            'base_unit_total_purchase_tax_price' => round(($total_cost_price / $baseQty) + ($total_cost_tax_price / $baseQty), 6),
            'base_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'base_unit_total_sale_tax_price' => round(($total_sale_price / $baseQty) + ($total_sale_tax_price / $baseQty), 6),
            'paid_unit_cost_price' => $paidUnitCost,
            'extra_paid_per_unit'  => $extraPaidPerUnit,
            'extra_paid_percent'   => $extraPaidPercent,
            'paid_extra_total_cost_price' => $paidExtraTotalCost,
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

        StockPrices::create([
            'purchase_id'                        => $purchase->id,
            'product_id'                         => $request->product,
            'category_id'                        => $request->category,
            'base_category_id'                   => $baseCategoryId,
            'base_stock'                         => round($baseQty, 1), // 1 digit allowed for quantities
            'category_stock'                     => round($request->quantity, 1),
            'category_unit_purchase_price'       => round($request->unit_cost_price, 6),
            'category_unit_purchase_tax_price'   => round($request->unit_cost_tax_amount, 6),
            'category_unit_total_purchase_tax_price'   => round($request->unit_cost_price + $request->unit_cost_tax_amount, 6),
            'category_unit_sale_price'           => round($request->unit_sale_price, 6),
            'category_unit_sale_tax_price'       => round($request->unit_sale_tax_amount, 6),
            'category_unit_total_sale_tax_price'   => round($request->unit_sale_price + $request->unit_sale_tax_amount, 6),
            'base_category_unit_purchase_price'  => round($total_cost_price / $baseQty, 6),
            'base_category_unit_purchase_tax_price' => round($total_cost_tax_price / $baseQty, 6),
            'base_category_unit_total_purchase_tax_price' => round(($total_cost_price / $baseQty) + ($total_cost_tax_price / $baseQty), 6),
            'base_category_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_category_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'base_category_unit_total_sale_tax_price' => round(($total_sale_price / $baseQty) + ($total_sale_tax_price / $baseQty), 6),
        ]);

        $stock = ProductStock::where('product_id', $productId)->first();

        if ($stock) {
            $stock->increment('current_stock', $baseQty);
            $stock->base_category_id = $baseCategoryId;
            $stock->save();
        } else {
            ProductStock::create([
                'product_id'       => $productId,
                'base_category_id' => $baseCategoryId,
                'current_stock'    => $baseQty,
            ]);
        }

        BaseStockSalePrice::create([
            'purchase_id'                        => $purchase->id,
            'product_id'                         => $request->product,
            'category_id'                        => $request->category,
            'base_category_id'                   => $baseCategoryId,
            'base_stock'                         => round($baseQty, 1), // 1 digit allowed for quantities
            'remaining_base_stock'                     => round($baseQty, 1),
            'base_category_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_category_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'expiry_date' => $request->expiry_date,
        ]);

        $notifications = notify("Purchase has been added");
        return redirect()->route('purchases.index')->with($notifications);
    }

    public function calculateBaseStock($productId, $selectedCategoryId, $quantity)
    {
        $params = ProductParameter::where('product_id', $productId)->get();

        // Build lookup: parent → [child => qty]
        $map = [];
        foreach ($params as $p) {
            // skip self-row (base category row: parent == child)
            if ($p->parent_category_id == $p->child_category_id) {
                continue;
            }
            $map[$p->parent_category_id][$p->child_category_id] = $p->quantity;
        }

        $currentCategory = $selectedCategoryId;
        $currentQty      = $quantity;

        // Traverse until no child exists
        while (isset($map[$currentCategory])) {
            $childId    = array_key_first($map[$currentCategory]);
            $multiplier = $map[$currentCategory][$childId];
            $currentQty = $currentQty * $multiplier;
            $currentCategory = $childId;
        }

        // $currentCategory = base category
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
        $sale_taxes      = Tax::all();

        // eager load purchase taxes + their tax info
        $purchase->load(['taxes.tax']);

        return view('admin.purchases.edit', compact(
            'title',
            'purchase',
            'categories',
            'suppliers',
            'products',
            'taxes',
            'sale_taxes'
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
            'paid_unit_cost_price' => 'nullable|numeric|min:0',
            'extra_paid_per_unit'  => 'nullable|numeric|min:0',
            'extra_paid_percent'   => 'nullable|numeric|min:0|max:100',
            'invoice_no'           => 'nullable|string|max:255',
        ]);

        $imageName = $purchase->image;
        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('storage/purchases'), $imageName);
        }

        [$baseCategoryId, $baseQty] = $this->calculateBaseStock($request->product, $request->category, $request->quantity);
        $oldQty = $purchase->base_quantity;
        $total_cost_price = $request->unit_cost_price * $request->quantity;
        $total_cost_tax_price = $request->total_cost_tax_amount;
        $total_sale_price = $request->unit_sale_price * $request->quantity;
        $total_sale_tax_price = $request->total_sale_tax_amount;
        $extraPaidPerUnit  = $request->extra_paid_per_unit ?? 0;
        $extraPaidPercent = $request->extra_paid_percent ?? 0;

        $paidUnitCost = $request->unit_cost_price + $extraPaidPerUnit;

        $paidExtraTotalCost = $paidUnitCost * $request->quantity;
        $purchase->update([
            'product_id'             => $request->product,
            'category_id'            => $request->category,
            'supplier_id'            => $request->supplier,
            'unit_cost_price'        => $request->unit_cost_price,
            'total_cost_price'       => $request->unit_cost_price * $request->quantity,
            'unit_cost_tax_amount'   => $request->unit_cost_tax_amount,
            'total_cost_tax_amount'  => $request->total_cost_tax_amount,
            'invoice_no'             => $request->invoice_no,
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
            'base_unit_purchase_price'  => round($total_cost_price / $baseQty, 6),
            'base_unit_purchase_tax_price' => round($total_cost_tax_price / $baseQty, 6),
            'base_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'total_sale_tax_amount'  => $request->total_sale_tax_amount,
            'base_unit_purchase_price'  => round($total_cost_price / $baseQty, 6),
            'base_unit_purchase_tax_price' => round($total_cost_tax_price / $baseQty, 6),
            'base_unit_total_purchase_tax_price' => round(($total_cost_price / $baseQty) + ($total_cost_tax_price / $baseQty), 6),
            'base_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'base_unit_total_purchase_tax_price' => round(($total_sale_price / $baseQty) + ($total_sale_tax_price / $baseQty), 6),
            'paid_unit_cost_price' => $paidUnitCost,
            'extra_paid_per_unit'  => $extraPaidPerUnit,
            'extra_paid_percent'   => $extraPaidPercent,
            'paid_extra_total_cost_price' => $paidExtraTotalCost,
        ]);

        // sync taxes
        $purchase->taxes()->delete(); // clear old
        if ($request->has('taxes')) {
            foreach ($request->taxes as $tax) {
                PurchaseTax::create([
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
                SaleTax::create([
                    'purchase_id' => $purchase->id,
                    'product_id'  => $request->product,
                    'tax_id'      => $tax['id'],
                    'tax_rate'    => $tax['rate'],
                    'tax_unit_amount' => $tax['unit'],
                    'tax_amount'  => $tax['total'],
                ]);
            }
        }

        $stockPrice = StockPrices::where('purchase_id', $purchase->id)->first();

        $data = [
            'product_id'                         => $request->product,
            'category_id'                        => $request->category,
            'base_category_id'                   => $baseCategoryId,
            'base_stock'                         => round($baseQty, 1), // 1 digit allowed for quantities
            'category_stock'                     => round($request->quantity, 1),
            'category_unit_purchase_price'       => round($request->unit_cost_price, 6),
            'category_unit_purchase_tax_price'   => round($request->unit_cost_tax_amount, 6),
            'category_unit_total_purchase_tax_price'   => round($request->unit_cost_price + $request->unit_cost_tax_amount, 6),
            'category_unit_sale_price'           => round($request->unit_sale_price, 6),
            'category_unit_sale_tax_price'       => round($request->unit_sale_tax_amount, 6),
            'category_unit_total_sale_tax_price'   => round($request->unit_sale_price + $request->unit_sale_tax_amount, 6),
            'base_category_unit_purchase_price'  => round($total_cost_price / $baseQty, 6),
            'base_category_unit_purchase_tax_price' => round($total_cost_tax_price / $baseQty, 6),
            'base_category_unit_total_purchase_tax_price' => round(($total_cost_price / $baseQty) + ($total_cost_tax_price / $baseQty), 6),
            'base_category_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_category_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'base_category_unit_total_sale_tax_price' => round(($total_sale_price / $baseQty) + ($total_sale_tax_price / $baseQty), 6),
        ];

        if ($stockPrice) {
            $stockPrice->update($data);
        } else {
            $data['purchase_id'] = $purchase->id;
            StockPrices::create($data);
        }

        $stock = ProductStock::where('product_id', $request->product)->first();
        if ($stock) {
            $difference = $baseQty - $oldQty;
            $stock->increment('current_stock', $difference);
            $stock->save();
        }

        $BaseStockPrice = BaseStockSalePrice::where('purchase_id', $purchase->id)->first();

        $dataBaseStock = [
            'purchase_id'                        => $purchase->id,
            'product_id'                         => $request->product,
            'category_id'                        => $request->category,
            'base_category_id'                   => $baseCategoryId,
            'base_stock'                         => round($baseQty, 1), // 1 digit allowed for quantities
            'remaining_base_stock'                     => round($baseQty, 1),
            'base_category_unit_sale_price'      => round($total_sale_price / $baseQty, 6),
            'base_category_unit_sale_tax_price'  => round($total_sale_tax_price / $baseQty, 6),
            'expiry_date' => $request->expiry_date,
        ];

        if ($BaseStockPrice) {
            $BaseStockPrice->update($dataBaseStock);
        } else {
            $dataBaseStock['purchase_id'] = $purchase->id;
            BaseStockSalePrice::create($dataBaseStock);
        }

        $notifications = notify("Purchase has been updated");
        return redirect()->route('purchases.index')->with($notifications);
    }

    public function reports(Request $request)
    {
        $title = 'purchase reports';
        $from_date = Carbon::now()->startOfMonth()->toDateString();
        $to_date = Carbon::now()->endOfMonth()->toDateString();
        $purchases = Purchase::whereBetween(DB::raw('DATE(created_at)'), array($from_date, $to_date))->latest('created_at')->get();
        return view('admin.purchases.reports', compact('title', 'purchases', 'from_date', 'to_date'));
    }

    public function generateReport(Request $request)
    {
        $this->validate($request, [
            'from_date' => 'required',
            'to_date' => 'required'
        ]);
        $title = 'purchases reports';
        $purchases = Purchase::whereBetween(DB::raw('DATE(created_at)'), array($request->from_date, $request->to_date))->latest('created_at')->get();
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        return view('admin.purchases.reports', compact(
            'purchases',
            'title',
            'from_date',
            'to_date'
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
        $purchase = Purchase::findOrFail($request->id);

        // Delete related purchase taxes
        PurchaseTax::where('purchase_id', $purchase->id)->delete();

        // Delete related sale taxes
        SaleTax::where('purchase_id', $purchase->id)->delete();

        // Delete related stock prices
        StockPrices::where('purchase_id', $purchase->id)->delete();

        // Delete related purchase stock (if you want to adjust stock, handle carefully)
        $productStock = ProductStock::where('product_id', $purchase->product_id)->first();

        if ($productStock) {
            $currentStock = $productStock->current_stock - $purchase->base_quantity;

            $productStock->update([
                'current_stock' => $currentStock,
            ]);
        }

        BaseStockSalePrice::where('purchase_id', $purchase->id)->delete();

        // Finally delete purchase
        $purchase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase and related records deleted successfully.'
        ]);
    }
}
