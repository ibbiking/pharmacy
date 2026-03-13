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
        $query = Invoice::with('items.product')->latest();

        if ($request->invoice_no) {
            $query->where('invoice_no', $request->invoice_no);
        }

        $invoices = $query->paginate(20);

        return view('admin.invoices.index', compact('invoices'));
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

            $success = $this->processItemReturn($invoice, $item, $request->return_qty, $request->reason);

            if (!$success) {
                return back()->with('error', 'Return quantity exceeds available quantity.');
            }

            // Recalculate invoice final totals
            $this->recalculateInvoiceTotals($invoice);

            DB::commit();

            return back()->with('success', 'Product returned successfully.');
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

            foreach ($invoice->items as $item) {
                $alreadyReturned = $item->returns->sum('qty_returned');
                $remainingQty = $item->qty - $alreadyReturned;
                if ($remainingQty <= 0) continue;

                $this->processItemReturn($invoice, $item, $remainingQty, $request->reason);
            }

            // Recalculate invoice final totals
            $this->recalculateInvoiceTotals($invoice);

            DB::commit();
            return back()->with('success', 'Invoice fully returned.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    // ==============================
    // 5️⃣ Unified Process Item Return
    // ==============================
    private function processItemReturn(Invoice $invoice, InvoiceItem $item, $returnQty, $reason)
    {
        $alreadyReturned = $item->returns->sum('qty_returned');
        $availableQty = $item->qty - $alreadyReturned;

        if ($returnQty > $availableQty) {
            return false;
        }

        // 1️⃣ Log return
        InvoiceItemReturn::create([
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $item->id,
            'product_id' => $item->product_id,
            'qty_returned' => $returnQty,
            'reason' => $reason,
            'handled_by' => auth()->id(),
        ]);

        // 2️⃣ Restore stock based on sale preference
        $baseReturnQty = $this->productController->convertToBaseQuantityOptimized(
            $item->product_id,
            $item->category_id,
            $returnQty
        );

        $this->restoreStockBySalePreference($item, $baseReturnQty);

        // 3️⃣ Deduct from totals accurately based on remaining unit discounts
        $unitDiscount = $availableQty > 0 ? ($item->discount_amount / $availableQty) : 0;
        $discountToDeduct = $unitDiscount * $returnQty;
        
        $baseAmountToDeduct = $item->price * $returnQty;
        $rowTotalToDeduct = $baseAmountToDeduct - $discountToDeduct;

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
        if ($invoice->invoice_discount_type === 'percent') {
            $invoice->invoice_discount_amount = $invoice->total * ($invoice->invoice_discount_value / 100);
        } else {
            $invoice->invoice_discount_amount = min($invoice->total, $invoice->invoice_discount_value);
        }
        
        $invoice->grand_total = $invoice->total - $invoice->invoice_discount_amount;
        
        if ($invoice->total <= 0) {
            $invoice->fully_returned = true;
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

}
