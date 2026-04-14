<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\BaseStockSalePrice;
use App\Models\InvoiceItemReturn;
use App\Models\ProductParameter;
use App\Models\InvoiceItem;
use App\Models\ProductPreference;
use App\Models\Preference;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    private $productController;

    public function __construct(ProductController $productController)
    {
        $this->productController = $productController;
    }

    // ==============================
    // 1️⃣ List invoices
    // ==============================
    public function index(Request $request)
    {
        $title = 'invoices';
        if ($request->ajax()) {
            $query = Invoice::with('items.product')->latest();

            if ($request->invoice_no) {
                $query->where('invoice_no', $request->invoice_no);
            }

            return DataTables::of($query)
                ->addColumn('date', function ($invoice) {
                    return $invoice->created_at->format('d-m-Y H:i');
                })
                ->addColumn('grand_total', function ($invoice) {
                    return number_format($invoice->grand_total, 2);
                })
                ->addColumn('cash_received', function ($invoice) {
                    return number_format($invoice->cash_received, 2);
                })
                ->addColumn('change_return', function ($invoice) {
                    return number_format($invoice->change_return, 2);
                })
                ->addColumn('action', function ($row) {
                    return '<a href="' . route("invoices.show", $row->invoice_no) . '" class="btn btn-sm btn-info">View</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.invoices.index', compact('title'));
    }

    // ==============================
    // 2️⃣ Show invoice with net qty, net total, base qty & price
    // ==============================
    public function show($invoice_no)
    {
        $invoice = Invoice::where('invoice_no', $invoice_no)
            ->with('items.product', 'items.category', 'items.returns')
            ->firstOrFail();

        foreach ($invoice->items as $item) {
            $returnedQty = $item->returns->sum('qty_returned');
            $item->net_qty = $item->qty - $returnedQty;

            // Net total including discount
            $item->net_total = ($item->price * $item->net_qty) - $item->discount_amount;

            // Base category quantity
            $item->base_qty = $this->productController->convertToBaseQuantityOptimized(
                $item->product_id,
                $item->category_id,
                $item->net_qty
            );

            // Base category price including tax
            $basePrice = $item->price;
            if ($item->is_tax_included && isset($item->tax_amount)) {
                $basePrice += $item->tax_amount;
            }

            $item->base_price = $this->productController->calculateCategoryPrice(
                $item->product_id,
                $item->category_id,
                $item->base_category_id,
                $basePrice
            );
        }

        $invoice->net_subtotal = $invoice->items->sum('net_total');

        return view('admin.invoices.show', compact('invoice'));
    }

    // ==============================
    // 3️⃣ Return single invoice item
    // ==============================
    public function returnProduct(Request $request, $invoice_no, $item_id)
    {
        $request->validate([
            'return_qty' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $invoice = Invoice::where('invoice_no', $invoice_no)->firstOrFail();
            $item = $invoice->items()->where('id', $item_id)->firstOrFail();

            $alreadyReturned = $item->returns->sum('qty_returned');
            $availableQty = $item->qty - $alreadyReturned;
            
            // Re-calculate the actual return value factored from unit discounts BEFORE the item is processed and mutated
            $unitDiscount = $availableQty > 0 ? ($item->discount_amount / $availableQty) : 0;
            $returnVal = ($item->price * $request->return_qty) - ($unitDiscount * $request->return_qty);
            $totalUnitDiscount = ($unitDiscount * $request->return_qty);

            $return_no = 'RET-' . date('YmdHis') . '-' . rand(100, 999);
            $clawback = 0;
            $success = $this->processItemReturn($invoice, $item, $request->return_qty, $request->reason, $return_no, $clawback);

            if (!$success) {
                return back()->with('error', 'Return quantity exceeds available quantity.');
            }

            // Recalculate invoice final totals
            $this->recalculateInvoiceTotals($invoice);

            DB::commit();

            // Prepare single item payload for print
            $productModel = \App\Models\Product::find($item->product_id);
            $categoryModel = \App\Models\Category::find($item->category_id);

            $printPayload = [
                'return_no' => $return_no,
                'metadata' => [
                    'global_discount_clawback' => $clawback,
                    'total_unit_discount' => $totalUnitDiscount,
                    'gross_subtotal' => $item->price * $request->return_qty
                ],
                'items' => [
                    [
                        'name' => $productModel->product_name ?? $item->name,
                        'strength' => $productModel && $productModel->strength ? $productModel->strength->name : '',
                        'category_name' => $categoryModel->name ?? '',
                        'qty' => $request->return_qty,
                        'price' => $item->price,
                        'gross_total' => $item->price * $request->return_qty,
                        'total' => $returnVal
                    ]
                ],
                'totalReturn' => $returnVal
            ];

            return back()->with('success', 'Product returned successfully.')
                         ->with('print_return_payload', $printPayload);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // ==============================
    // 4️⃣ Return full invoice
    // ==============================
    public function returnInvoice(Request $request, $invoice_no)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        DB::beginTransaction();
        try {
            $invoice = Invoice::where('invoice_no', $invoice_no)
                ->with('items.returns')
                ->firstOrFail();

            $returnedItemsPayload = [];
            $totalClawback = 0;
            $grossUnitDiscount = 0;
            $grossSubtotal = 0;
            $return_no = 'RET-' . date('YmdHis') . '-' . rand(100, 999);

            foreach ($invoice->items as $item) {
                $alreadyReturned = $item->returns->sum('qty_returned');
                $remainingQty = $item->qty - $alreadyReturned;
                if ($remainingQty <= 0) continue;

                $clawback = 0;
                $this->processItemReturn($invoice, $item, $remainingQty, $request->reason, $return_no, $clawback);
                $totalClawback += $clawback;

                $productModel = \App\Models\Product::find($item->product_id);
                $categoryModel = \App\Models\Category::find($item->category_id);
                $unitDiscount = $item->qty > 0 ? ($item->discount_amount / $item->qty) : 0;
                $returnVal = ($item->price * $remainingQty) - ($unitDiscount * $remainingQty);
                $grossUnitDiscount += ($unitDiscount * $remainingQty);
                $grossSubtotal += ($item->price * $remainingQty);

                $returnedItemsPayload[] = [
                    'name' => $productModel->product_name ?? $item->name,
                    'strength' => $productModel && $productModel->strength ? $productModel->strength->name : '',
                    'category_name' => $categoryModel->name ?? '',
                    'qty' => $remainingQty,
                    'price' => $item->price,
                    'gross_total' => $item->price * $remainingQty,
                    'total' => $returnVal
                ];
            }

            // Recalculate invoice final totals
            $this->recalculateInvoiceTotals($invoice);

            \App\Models\InvoiceHistory::create([
                'invoice_id' => $invoice->id,
                'action' => 'Fully Returned',
                'description' => 'Entire invoice was returned.',
                'user_id' => auth()->id(),
            ]);

            \App\Models\ReturnHistory::create([
                'return_no' => $return_no,
                'invoice_id' => $invoice->id,
                'action' => 'Fully Returned',
                'description' => 'Entire invoice was returned.',
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            $fullPayload = [
                'return_no' => $return_no,
                'metadata' => [
                    'global_discount_clawback' => $totalClawback,
                    'total_unit_discount' => $grossUnitDiscount,
                    'gross_subtotal' => $grossSubtotal
                ],
                'items' => $returnedItemsPayload,
                'totalReturn' => $grossSubtotal - $grossUnitDiscount
            ];

            return back()->with('success', 'Invoice fully returned.')
                         ->with('print_return_payload', $fullPayload);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // ==============================
    // 5️⃣ Unified Process Item Return
    // ==============================
    private function processItemReturn(Invoice $invoice, InvoiceItem $item, $returnQty, $reason, $return_no, &$clawbackOutput = 0)
    {
        $alreadyReturned = $item->returns->sum('qty_returned');
        $availableQty = $item->qty - $alreadyReturned;

        if ($returnQty > $availableQty) {
            return false;
        }

        // 3️⃣ Deduct from totals accurately based on remaining unit discounts
        $unitDiscount = $availableQty > 0 ? ($item->discount_amount / $availableQty) : 0;
        $discountToDeduct = $unitDiscount * $returnQty;
        
        $baseAmountToDeduct = $item->price * $returnQty;
        $rowTotalToDeduct = $baseAmountToDeduct - $discountToDeduct;

        // 4️⃣ Claw back Global Invoice Discount Proportionally
        $ratio = $invoice->total > 0 ? ($rowTotalToDeduct / $invoice->total) : 0;
        $globalDiscountClawback = $invoice->invoice_discount_amount * $ratio;

        // 1️⃣ Log return
        InvoiceItemReturn::create([
            'return_no' => $return_no,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $item->id,
            'product_id' => $item->product_id,
            'qty_returned' => $returnQty,
            'unit_discount_deducted' => $discountToDeduct,
            'global_discount_clawback' => $globalDiscountClawback,
            'reason' => $reason,
            'handled_by' => auth()->id(),
        ]);
        
        \App\Models\InvoiceHistory::create([
            'invoice_id' => $invoice->id,
            'action' => 'Item Returned',
            'description' => "Returned {$returnQty}x {$item->name} (Return No: {$return_no}).",
            'user_id' => auth()->id(),
        ]);

        \App\Models\ReturnHistory::create([
            'return_no' => $return_no,
            'invoice_id' => $invoice->id,
            'action' => 'Item Returned',
            'description' => "Returned {$returnQty}x {$item->name}.",
            'user_id' => auth()->id(),
        ]);
        
        $invoice->invoice_discount_amount -= $globalDiscountClawback;
        $clawbackOutput = $globalDiscountClawback;

        // 2️⃣ Restore stock based on sale preference
        $baseReturnQty = $this->productController->convertToBaseQuantityOptimized(
            $item->product_id,
            $item->category_id,
            $returnQty
        );

        $this->restoreStockBySalePreference($item, $baseReturnQty);

        $productStock = \App\Models\ProductStock::where('product_id', $item->product_id)->first();
        if ($productStock) {
            $productStock->current_stock += $baseReturnQty;
            $productStock->save();
        }

        $item->discount_amount -= $discountToDeduct;
        $item->row_total -= $rowTotalToDeduct;
        $item->save();

        $invoice->subtotal -= $baseAmountToDeduct;
        $invoice->discount -= $discountToDeduct;
        $invoice->total -= $rowTotalToDeduct;
        
        return true;
    }

    // ==============================
    // 6️⃣ Recalculate Invoice Totals
    // ==============================
    private function recalculateInvoiceTotals(Invoice $invoice)
    {
        $invoice->grand_total = $invoice->total - $invoice->invoice_discount_amount;
        
        if ($invoice->total <= 0) {
            $invoice->fully_returned = true;
            $invoice->invoice_discount_amount = 0;
            $invoice->grand_total = 0;
        }
        
        $invoice->save();
    }

    // ==============================
    // 5️⃣ Restore stock by sale preference (no raw queries)
    // ==============================
    private function restoreStockBySalePreference($item, $baseReturnQty)
    {
        $targetPrice = round((float)$item->base_category_price, 4);
        $preferenceSlug = $item->sale_preference_slug ?? 'static-price';

        // Fetch all batches for this product and base category
        // Order by ID DESC to restore stock to the most recently active batches first
        $stockRows = BaseStockSalePrice::where('product_id', $item->product_id)
            ->where('base_category_id', $item->base_category_id)
            ->orderBy('id', 'desc')
            ->get();

        $matchingRows = [];

        if ($preferenceSlug === 'stock-wise-price') {
            foreach ($stockRows as $row) {
                $rowPrice = $row->base_category_unit_sale_price;
                if ($item->is_tax_included) {
                    $rowPrice += ($row->base_category_unit_sale_tax_price ?? 0);
                }
                
                // Allow for a small float precision drift
                if (abs($rowPrice - $targetPrice) < 0.001) {
                    $matchingRows[] = $row;
                }
            }
        } else {
            // Not stock-wise-price, we do NOT force match the price.
            $matchingRows = $stockRows->all();
        }

        // If no exactly matching price is found (for stock-wise-price), fallback to all rows for this product
        if (empty($matchingRows)) {
            $matchingRows = $stockRows->all();
        }

        $remainingToRestore = $baseReturnQty;

        // 1) Distribute the returned quantity within the base_stock limit sequentially over EXACT matching rows FIRST
        foreach ($matchingRows as $baseStock) {
            if ($remainingToRestore <= 0) break;

            $spaceAvailable = $baseStock->base_stock - $baseStock->remaining_base_stock;
            if ($spaceAvailable > 0) {
                $restoreAmount = min($spaceAvailable, $remainingToRestore);
                $baseStock->remaining_base_stock += $restoreAmount;
                $baseStock->save();
                
                $remainingToRestore -= $restoreAmount;
            }
        }

        // 2) If there is still remaining quantity, fallback to ALL available rows for this product to prevent overflow 
        if ($remainingToRestore > 0 && !$stockRows->isEmpty()) {
            foreach ($stockRows as $baseStock) {
                if ($remainingToRestore <= 0) break;

                $spaceAvailable = $baseStock->base_stock - $baseStock->remaining_base_stock;
                if ($spaceAvailable > 0) {
                    $restoreAmount = min($spaceAvailable, $remainingToRestore);
                    $baseStock->remaining_base_stock += $restoreAmount;
                    $baseStock->save();
                    
                    $remainingToRestore -= $restoreAmount;
                }
            }
        }

        // 3) ONLY if it STILL exceeds ALL available capacity in the entire system, forcefully dump into the newest batch
        if ($remainingToRestore > 0 && !$stockRows->isEmpty()) {
            $baseStock = $stockRows->first();
            $baseStock->remaining_base_stock += $remainingToRestore;
            $baseStock->save();
        }
    }

    // ==============================
    // 7️⃣ Print Return Receipt
    // ==============================
    public function printReturnReceipt(Request $request)
    {
        $payloadRaw = $request->input('payload');
        if (!$payloadRaw) {
            return response('No payload provided', 400);
        }

        $payload = json_decode($payloadRaw, true);
        if (!$payload || empty($payload['returnedItems']['items'])) {
            // Handle legacy basic array format silently or throw, adapt to new grouped array
            if (isset($payload['returnedItems']) && is_array($payload['returnedItems']) && !isset($payload['returnedItems']['items'])) {
                $items = $payload['returnedItems'];
                $metadata = ['global_discount_clawback' => 0, 'total_unit_discount' => 0];
            } else {
                $items = $payload['returnedItems']['items'] ?? [];
                $metadata = $payload['returnedItems']['metadata'] ?? [];
            }
            $return_no = null; // Default for legacy format
        } else {
            $items = $payload['returnedItems']['items'];
            $metadata = $payload['returnedItems']['metadata'];
            $return_no = $payload['returnedItems']['return_no'] ?? null;
        }

        $invoice_no = $payload['invoice_no'] ?? 'Unknown';
        $totalReturn = isset($payload['totalReturn']) ? floatval($payload['totalReturn']) : 0;

        $invoice = Invoice::where('invoice_no', $invoice_no)->first();
        $invoice_date = $invoice ? $invoice->created_at->format('d-M-y g:ia') : null;

        return view('admin.invoices.return-receipt-print', [
            'invoice_no' => $invoice_no,
            'invoice_date' => $invoice_date,
            'return_no'  => $return_no,
            'returnedItems' => $items,
            'metadata' => $metadata,
            'totalReturn' => $totalReturn
        ]);
    }

    // ==============================
    // 9️⃣ Reprint existing Invoice purely from DB
    // ==============================
    public function printInvoice($invoice_no)
    {
        $invoice = Invoice::where('invoice_no', $invoice_no)->with(['items', 'business'])->firstOrFail();

        $cartItems = [];
        $subtotal = 0;
        $grossSubtotal = 0;
        $totalItemDiscount = 0;
        
        foreach ($invoice->items as $item) {
            $productModel = \App\Models\Product::find($item->product_id);
            $categoryModel = \App\Models\Category::find($item->category_id);
            
            $cartItems[] = [
                'name' => $item->name,
                'price' => $item->price,
                'qty' => $item->qty,
                'discount_selected_type' => $item->discount_type,
                'discount_percent' => $item->discount_type === 'percent' ? $item->discount_value : 0,
                'discount_amount' => $item->discount_type === 'amount' ? $item->discount_value : 0,
                'total' => $item->row_total,
                'category_name' => $categoryModel ? $categoryModel->name : '',
                'strength' => $productModel && $productModel->strength ? $productModel->strength->name : ''
            ];
            
            $subtotal += $item->row_total;
            $grossSubtotal += ($item->price * $item->qty);
            $totalItemDiscount += $item->discount_amount;
        }

        return view('admin.pos.receipt-print', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'grossSubtotal' => $grossSubtotal,
            'totalItemDiscount' => $totalItemDiscount,
            'invoiceDiscountValue' => $invoice->invoice_discount_value,
            'invoiceDiscountType' => $invoice->invoice_discount_type,
            'invoiceDiscount' => $invoice->invoice_discount_amount,
            'grandTotal' => $invoice->grand_total,
            'cashReceived' => $invoice->cash_received,
            'changeReturn' => $invoice->change_return,
            'invoice_no' => $invoice->invoice_no,
            'business' => $invoice->business
        ]);
    }
}
