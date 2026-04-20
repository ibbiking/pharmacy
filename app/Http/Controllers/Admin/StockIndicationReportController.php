<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Preference;
use App\Models\Category;
use Yajra\DataTables\Facades\DataTables;

class StockIndicationReportController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Stock Indication Report';
        if ($request->ajax()) {
            $business_id = session('business_id');
            $globalPref = Preference::where('business_id', $business_id)->where('slug', 'global_min_indicated_qty')->first();
            $globalMin = $globalPref ? (float)$globalPref->preference : null;

            $products = Product::with(['company', 'type', 'stock', 'parameters'])
                ->where('business_id', $business_id)
                ->real()
                ->get()
                ->filter(function ($product) use ($globalMin) {
                    $minQty = $product->min_indicated_qty;
                    $minQtyCat = $product->min_qty_category_id;
                    
                    if (is_null($minQty) && !is_null($globalMin)) {
                        $minQty = $globalMin;
                        $ctrl = app(\App\Http\Controllers\Admin\ProductController::class);
                        $minQtyCat = $ctrl->getMainCategoryId($product->id);
                    }

                    if ($minQty !== null && $minQtyCat) {
                        $product->computed_min_qty = $minQty;
                        $product->computed_min_cat_id = $minQtyCat;
                        
                        $ctrl = app(\App\Http\Controllers\Admin\ProductController::class);
                        $targetStock = $ctrl->getCategoryStock($product->id, $minQtyCat, $product->stock->current_stock ?? 0);
                        $product->computed_current_stock = $targetStock;
                        
                        return true;
                    }
                    return false;
                });

            return DataTables::of($products)
                ->addColumn('category', function ($row) {
                    $cat = Category::find($row->computed_min_cat_id);
                    return $cat ? $cat->name : 'Unknown';
                })
                ->addColumn('limit_qty', function ($row) {
                    return number_format($row->computed_min_qty, 2);
                })
                ->addColumn('current_qty', function ($row) {
                    $badge = $row->computed_current_stock <= $row->computed_min_qty ? 'badge-danger' : 'badge-success';
                    return '<span class="badge ' . $badge . '">' . number_format($row->computed_current_stock, 2) . '</span>';
                })
                ->rawColumns(['current_qty'])
                ->make(true);
        }

        return view('admin.reports.stock_indication', compact('title'));
    }

    public function unaligned(Request $request)
    {
        $title = 'Unaligned Products Report';
        if ($request->ajax()) {
            $business_id = session('business_id');
            $globalPref = Preference::where('business_id', $business_id)->where('slug', 'global_min_indicated_qty')->first();
            $globalMin = $globalPref ? (float)$globalPref->preference : null;

            $products = Product::with(['company', 'type', 'stock', 'parameters'])
                ->where('business_id', $business_id)
                ->real()
                ->get()
                ->filter(function ($product) use ($globalMin) {
                    $minQty = $product->min_indicated_qty;
                    $minQtyCat = $product->min_qty_category_id;
                    
                    if (is_null($minQty) && !is_null($globalMin)) {
                        $minQty = $globalMin;
                        $ctrl = app(\App\Http\Controllers\Admin\ProductController::class);
                        $minQtyCat = $ctrl->getMainCategoryId($product->id);
                    }

                    // Unaligned are products where limits cannot be determined
                    return is_null($minQty) || is_null($minQtyCat);
                });

            return DataTables::of($products)
                ->addColumn('company', function ($product) {
                    return $product->company->name ?? '-';
                })
                ->addColumn('type', function ($product) {
                    return $product->type->name ?? '-';
                })
                ->make(true);
        }

        return view('admin.reports.stock_unaligned', compact('title'));
    }
}
