<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemReturn;
use App\Models\Product;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $title = 'Sales Report';
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', Carbon::now()->endOfMonth()->toDateString());

        if ($request->ajax()) {
            $invoices = Invoice::whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])->latest();

            return DataTables::of($invoices)
                ->filterColumn('date', function ($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(created_at, '%d M, %Y') like ?", ["%$keyword%"]);
                })
                ->addColumn('date', function ($invoice) {
                    return date_format($invoice->created_at, "d M, Y");
                })
                ->addColumn('subtotal', function ($invoice) {
                    return number_format($invoice->subtotal, 2);
                })
                ->addColumn('discount', function ($invoice) {
                    return number_format($invoice->discount + $invoice->invoice_discount_amount, 2);
                })
                ->addColumn('grand_total', function ($invoice) {
                    return '<b>' . number_format($invoice->grand_total, 2) . '</b>';
                })
                ->addColumn('action', function ($invoice) {
                    return '<a href="' . route('invoices.show', $invoice->invoice_no) . '" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> View</a> <a href="javascript:void(0)" onclick="printInvoiceReceipt(\'' . route('invoices.print', $invoice->invoice_no) . '\')" class="btn btn-sm btn-outline-primary mt-1"><i class="fas fa-print"></i> Print</a>';
                })
                ->rawColumns(['grand_total', 'action'])
                ->make(true);
        }

        // Header Metrics natively queried for maximum velocity
        $totalSales = Invoice::whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])->sum('subtotal');
        $totalDiscount = Invoice::whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])
                         ->sum(DB::raw('discount + invoice_discount_amount')) ?? 0;

        $totalReturnedAmount = InvoiceItemReturn::whereBetween(DB::raw('DATE(invoice_item_returns.created_at)'), [$fromDate, $toDate])
             ->join('invoice_items', 'invoice_item_returns.invoice_item_id', '=', 'invoice_items.id')
             ->sum(DB::raw('invoice_item_returns.qty_returned * invoice_items.price'));

        // Net Sales corresponds to the true total cash theoretically collected (factoring in discounts and subsequent returns)
        $netSales = Invoice::whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate])->sum('grand_total');
        
        return view('admin.reports.sales', compact(
            'title', 'fromDate', 'toDate', 'totalSales', 'totalDiscount', 'totalReturnedAmount', 'netSales'
        ));
    }

    /**
     * Get the exact base unit purchase cost for a specific invoice item based on its sale preference.
     */
    private function getBaseUnitCost(InvoiceItem $item)
    {
        $product_id = $item->product_id;
        $preference = $item->sale_preference_slug ?? 'static-price';
        $taxIncluded = $item->is_tax_included;

        $baseUnitCost = 0;

        // If stock-wise-price, try to find the exact purchase batch
        if ($preference === 'stock-wise-price') {
            $targetPrice = round((float)$item->base_category_price, 4);
            $stockRows = \App\Models\BaseStockSalePrice::where('product_id', $product_id)
                ->where('base_category_id', $item->base_category_id)
                ->orderBy('id', 'desc')
                ->get();
            
            $purchase_id = null;
            foreach ($stockRows as $row) {
                $rowPrice = (float)$row->base_category_unit_sale_price;
                if ($taxIncluded) {
                    $rowPrice += (float)($row->base_category_unit_sale_tax_price ?? 0);
                }
                if (abs($rowPrice - $targetPrice) < 0.001) {
                    $purchase_id = $row->purchase_id;
                    break;
                }
            }
            
            if ($purchase_id) {
                $purchase = Purchase::find($purchase_id);
                if ($purchase) {
                    $paidTotal = $purchase->paid_extra_total_cost_price > 0 ? $purchase->paid_extra_total_cost_price : $purchase->total_cost_price;
                    $basePaidUnit = $purchase->base_quantity > 0 ? ($paidTotal / $purchase->base_quantity) : 0;
                    
                    $baseUnitCost = $taxIncluded ? ($basePaidUnit + $purchase->base_unit_purchase_tax_price) : $basePaidUnit;
                }
            }
        }
        
        // For static-price, check ProductParameter for static_category_unit_purchase_price
        if ($preference === 'static-price') {
            $param = \App\Models\ProductParameter::where('product_id', $product_id)
                ->where('child_category_id', $item->base_category_id)
                ->first();
                
            if ($param && $param->static_category_unit_purchase_price > 0) {
                // Return exactly what's configured, assuming user configured it.
                $baseUnitCost = $param->static_category_unit_purchase_price;
            }
        }
        
        // For previous-inventory-price, or if stock-wise/static failed to find a match:
        if ($baseUnitCost == 0) {
            $lastPurchase = Purchase::where('product_id', $product_id)
                ->latest('created_at')
                ->first();
                
            if ($lastPurchase) {
                 $paidTotal = $lastPurchase->paid_extra_total_cost_price > 0 ? $lastPurchase->paid_extra_total_cost_price : $lastPurchase->total_cost_price;
                 $basePaidUnit = $lastPurchase->base_quantity > 0 ? ($paidTotal / $lastPurchase->base_quantity) : 0;
                 
                 $baseUnitCost = $taxIncluded ? ($basePaidUnit + $lastPurchase->base_unit_purchase_tax_price) : $basePaidUnit;
            }
        }
        
        return $baseUnitCost;
    }

    /**
     * Display the Profit & Loss Report.
     */
    public function profitLoss(Request $request)
    {
        $title = 'Profit and Loss Report';
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', Carbon::now()->endOfMonth()->toDateString());

        // Fetch invoice items from invoices created in the date range
        $invoiceItems = InvoiceItem::whereHas('invoice', function($q) use ($fromDate, $toDate) {
                $q->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate]);
            })
            ->with(['invoice', 'product', 'returns', 'category'])
            ->latest()
            ->get();

        $totalRevenue = 0;
        $totalCost = 0;
        $totalReturnedRevenue = 0;
        $totalReturnedCost = 0;

        $reportData = [];

        foreach ($invoiceItems as $item) {
            $alreadyReturnedQty = $item->returns->sum('qty_returned');
            $netQty = $item->qty - $alreadyReturnedQty;

            // Properly proportion the base quantity for packaging independence
            $ratio = $item->qty > 0 ? ($netQty / $item->qty) : 0;
            $netBaseQty = $item->base_quantity * $ratio;
            
            $returnedRatio = $item->qty > 0 ? ($alreadyReturnedQty / $item->qty) : 0;
            $returnedBaseQty = $item->base_quantity * $returnedRatio;

            // Fetch the precise true cost mapped from the batch logic
            $unitCost = $this->getBaseUnitCost($item);
            
            // The item->row_total already has the ITEM discount deducted, AND if returns happened, it's ALREADY updated by InvoiceController
            $itemNetRevenue = $item->row_total; 
            
            // Proportionately apply any GLOBAL invoice discount to this item's revenue
            $invoice = $item->invoice;
            if ($invoice && $invoice->invoice_discount_amount > 0 && $invoice->total > 0) {
                $discountRatio = $invoice->invoice_discount_amount / $invoice->total;
                $apportionedDiscount = $itemNetRevenue * $discountRatio;
                $itemNetRevenue -= $apportionedDiscount;
            }
            
            // Total cost for net qty
            $itemNetCost = $netBaseQty * $unitCost;

            $itemProfit = $itemNetRevenue - $itemNetCost;

            $totalRevenue += $itemNetRevenue;
            $totalCost += $itemNetCost;

            // Approximate the stats for what was returned
            $returnedRevenue = 0;
            if ($alreadyReturnedQty > 0) {
                // Estimate the lost revenue per returned unit based on original price
                $approxReturnRevenue = $alreadyReturnedQty * $item->price;
                if ($invoice && $invoice->invoice_discount_amount > 0 && $invoice->total > 0) {
                    $approxReturnRevenue -= $approxReturnRevenue * ($invoice->invoice_discount_amount / $invoice->total);
                }
                $returnedRevenue = $approxReturnRevenue;
            }
            
            $returnedCost = $returnedBaseQty * $unitCost;
            
            $totalReturnedRevenue += $returnedRevenue;
            $totalReturnedCost += $returnedCost;

            $reportData[] = [
                'date' => $item->created_at->format('Y-m-d'),
                'invoice_no' => $item->invoice->invoice_no,
                'product_name' => $item->name,
                'category_name' => $item->category ? $item->category->name : 'N/A',
                'net_qty' => $netQty,
                'returned_qty' => $alreadyReturnedQty,
                'unit_cost' => $unitCost, // Represents cost per completely base-level 1 unit (ie stripped tablet/ml)
                'net_revenue' => $itemNetRevenue,
                'net_cost' => $itemNetCost,
                'profit' => $itemProfit,
            ];
        }

        $netProfit = $totalRevenue - $totalCost;
        
        $totalNetQty = collect($reportData)->sum('net_qty');
        $totalReturnedQty = collect($reportData)->sum('returned_qty');
        $sumNetRevenue = collect($reportData)->sum('net_revenue');
        $sumNetCost = collect($reportData)->sum('net_cost');
        $sumProfit = collect($reportData)->sum('profit');

        if ($request->ajax()) {
            return DataTables::of(collect($reportData))
                ->addColumn('date', function ($row) { return date('d M, Y', strtotime($row['date'])); })
                ->addColumn('invoice_no', function ($row) { return '<a href="'.route("invoices.show", $row["invoice_no"]).'">'.$row["invoice_no"].'</a>'; })
                ->addColumn('returned_qty', function ($row) { return $row['returned_qty'] > 0 ? '<span class="badge badge-danger">'.$row["returned_qty"].'</span>' : '0'; })
                ->addColumn('net_revenue', function ($row) { return number_format($row['net_revenue'], 2); })
                ->addColumn('net_cost', function ($row) { return number_format($row['net_cost'], 2); })
                ->addColumn('profit', function ($row) { return '<b class="' . ($row['profit'] >= 0 ? "text-success" : "text-danger") . '">' . number_format($row['profit'], 2) . '</b>'; })
                ->rawColumns(['invoice_no', 'returned_qty', 'profit'])
                ->make(true);
        }

        return view('admin.reports.profit_loss', compact(
            'title', 'fromDate', 'toDate',
            'totalRevenue', 'totalCost', 'netProfit',
            'totalReturnedRevenue', 'totalReturnedCost', 'totalNetQty', 'totalReturnedQty', 'sumNetRevenue', 'sumNetCost', 'sumProfit'
        ));
    }

    /**
     * Display the Expiry Report.
     */
    public function expiry(Request $request)
    {
        $title = 'Expiry Report';
        $duration = $request->input('duration', 6); // Default 6 months
        
        $untilDate = Carbon::today()->addMonths($duration)->toDateString();
        $today = Carbon::today()->toDateString();
        
        if ($request->ajax()) {
            $query = Purchase::with(['product', 'supplier', 'category'])
                ->whereNotNull('expiry_date')
                ->where('quantity', '>', 0)
                ->whereDate('expiry_date', '<=', $untilDate);

            return DataTables::of($query)
                ->filterColumn('product', function($query, $keyword) {
                    $query->whereHas('product', function($q) use($keyword) { $q->where('product_name', 'like', "%{$keyword}%"); });
                })
                ->filterColumn('category', function($query, $keyword) {
                    $query->whereHas('category', function($q) use($keyword) { $q->where('name', 'like', "%{$keyword}%"); });
                })
                ->filterColumn('purchase_date', function($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(purchases.created_at, '%d M, %Y') like ?", ["%$keyword%"]);
                })
                ->filterColumn('expiry_date', function($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(purchases.expiry_date, '%d M, %Y') like ?", ["%$keyword%"]);
                })
                ->addColumn('product', function ($purchase) {
                    $image = '';
                    if (!empty($purchase->image)) {
                        $image = '<span class="avatar avatar-sm mr-2"><img class="avatar-img" src="' . asset("storage/purchases/" . $purchase->image) . '" alt="product image"></span>';
                    }
                    return $image . ($purchase->product->product_name ?? 'Unknown');
                })
                ->addColumn('category', function ($purchase) {
                    return $purchase->category->name ?? 'N/A';
                })
                ->addColumn('batch_no', function ($purchase) {
                    return $purchase->batch_no ?? 'N/A';
                })
                ->addColumn('quantity', function ($purchase) {
                    return $purchase->quantity;
                })
                ->addColumn('purchase_date', function ($purchase) {
                    return date_format(date_create($purchase->created_at),"d M, Y");
                })
                ->addColumn('expiry_date', function ($purchase) {
                    return '<b>' . date_format(date_create($purchase->expiry_date),"d M, Y") . '</b>';
                })
                ->addColumn('status', function ($purchase) {
                    $expiryDate = \Carbon\Carbon::parse($purchase->expiry_date);
                    $now = \Carbon\Carbon::now();
                    $isPast = $expiryDate->isPast();
                    $daysRemaining = $now->diffInDays($expiryDate, false);
                    if($isPast) {
                        return 'Expired (' . abs((int)$daysRemaining) . ' days ago)';
                    } else {
                        return 'Expire in ' . (int)$daysRemaining . ' days';
                    }
                })
                ->setRowClass(function ($purchase) {
                    $expiryDate = \Carbon\Carbon::parse($purchase->expiry_date);
                    $now = \Carbon\Carbon::now();
                    $isPast = $expiryDate->isPast();
                    $daysRemaining = $now->diffInDays($expiryDate, false);
                    if ($isPast || $daysRemaining <= 30) {
                        return 'bg-danger text-white';
                    } elseif ($daysRemaining <= 90) {
                        return 'bg-warning text-dark';
                    }
                    return '';
                })
                ->rawColumns(['product', 'expiry_date'])
                ->make(true);
        }

        // Widget computations natively in SQL
        $criticalCount = Purchase::whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::today()->addDays(30)->toDateString())
            ->count();

        $upcomingCount = Purchase::whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '>', Carbon::today()->addDays(30)->toDateString())
            ->whereDate('expiry_date', '<=', Carbon::today()->addDays(90)->toDateString())
            ->count();

        $totalCount = Purchase::whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', $untilDate)
            ->count();

        return view('admin.reports.expiry', compact('title', 'duration', 'criticalCount', 'upcomingCount', 'totalCount'));
    }
}
