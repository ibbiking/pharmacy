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
use App\Models\Preference;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Models\BaseStockSalePrice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;

class POSController extends Controller
{
    private $productController;
    private $purchaseController;

    public function __construct()
    {
        $this->productController = new ProductController();
        $this->purchaseController = new PurchaseController();
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $business = session('business_id') ? \App\Models\Business::find(session('business_id')) : null;
        $nextInvoiceNo = ($business && $business->isFbrInvoicing())
            ? null
            : \App\Models\Invoice::previewNextLocalInvoiceNo();

        return view('admin.pos.index', compact('nextInvoiceNo'));
    }

    /**
     * Cosmetic preview of the next local invoice number, re-fetched by the
     * live receipt after each completed sale (the page never reloads).
     */
    public function nextInvoiceNumber()
    {
        $business = session('business_id') ? \App\Models\Business::find(session('business_id')) : null;

        if ($business && $business->isFbrInvoicing()) {
            return response()->json(['invoice_no' => null]);
        }

        return response()->json(['invoice_no' => \App\Models\Invoice::previewNextLocalInvoiceNo()]);
    }

    /**
     * Live tax preview for the POS cart, called whenever the cart changes so
     * the on-screen receipt (and Grand Total / Change Return, since Sale Tax
     * is an additive charge) matches what will actually be saved. Read-only —
     * mirrors the same computeSaleTaxBreakdown() used by saveInvoice(), split
     * into the additive "salesTaxes" (added to the total) and disclosure-only
     * "gstTaxes" (shown but not added).
     */
    public function cartTaxBreakdown(Request $request)
    {
        $cartInput = $request->input('cart', []);
        $cartData = is_array($cartInput) ? $cartInput : json_decode($cartInput, true);

        if (!is_array($cartData)) {
            return response()->json(['salesTaxes' => [], 'gstTaxes' => [], 'salesTaxTotal' => 0, 'gstTotal' => 0]);
        }

        $salesTaxBuckets = [];
        $gstBuckets = [];

        foreach ($cartData as $item) {
            if (!isset($item['id'], $item['qty'], $item['price'])) {
                continue;
            }

            $qty = (float) $item['qty'];
            $price = (float) $item['price'];
            if ($qty <= 0 || $price <= 0) {
                continue;
            }

            $discountSelectedType = $item['discount_selected_type'] ?? 'percent';
            $base = $price * $qty;
            $discountAmount = $discountSelectedType === 'percent'
                ? $base * ((float) ($item['discount_percent'] ?? 0) / 100)
                : (float) ($item['discount_amount'] ?? 0);
            $rowTotal = max(0, $base - min($discountAmount, $base));

            [, $baseQty] = $this->purchaseController
                ->calculateBaseStock($item['id'], $item['category_id'] ?? null, $qty);

            foreach ($this->computeSaleTaxBreakdown($item['id'], $baseQty, $rowTotal) as $bucket) {
                if (round($bucket['amount'], 2) <= 0) {
                    continue;
                }

                $key = $bucket['tax_id'] ?? 'gst';

                if ($bucket['tax_id'] === null) {
                    if (!isset($gstBuckets[$key])) {
                        $gstBuckets[$key] = ['tax_id' => null, 'name' => $bucket['name'], 'amount' => 0];
                    }
                    $gstBuckets[$key]['amount'] += $bucket['amount'];
                } else {
                    if (!isset($salesTaxBuckets[$key])) {
                        $salesTaxBuckets[$key] = ['tax_id' => $bucket['tax_id'], 'name' => $bucket['name'], 'amount' => 0];
                    }
                    $salesTaxBuckets[$key]['amount'] += $bucket['amount'];
                }
            }
        }

        $formatBuckets = function ($buckets) {
            return array_map(function ($b) {
                $b['amount'] = round($b['amount'], 2);
                return $b;
            }, array_values($buckets));
        };

        $salesTaxes = $formatBuckets($salesTaxBuckets);
        $gstTaxes = $formatBuckets($gstBuckets);

        return response()->json([
            'salesTaxes' => $salesTaxes,
            'gstTaxes' => $gstTaxes,
            'salesTaxTotal' => round(array_sum(array_column($salesTaxes, 'amount')), 2),
            'gstTotal' => round(array_sum(array_column($gstTaxes, 'amount')), 2),
        ]);
    }

    public function getProductDiscountInfo($id)
    {
        $product = Product::select('id', 'discount', 'lock_max_discount')->find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'discount' => (float) $product->discount,
            'lock_max_discount' => (bool) $product->lock_max_discount,
        ]);
    }

    public function printReceipt(Request $request)
    {
        try {
            // Get and validate cart data
            $cartJson = $request->input('cart', '[]');
            $cartData = json_decode($cartJson, true);

            // Check if JSON decoding was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid cart data format');
            }

            if (!is_array($cartData)) {
                throw new \Exception('Cart data must be an array');
            }

            $invoiceDiscountValue = floatval($request->input('invoice_discount_value', 0));
            $invoiceDiscountType = $request->input('invoice_discount_type', 'amount');
            $cashReceived = floatval($request->input('cash_received', 0));
            $changeReturn = floatval($request->input('change_return', 0));

            $invoiceNo = null;
            $business = null;
            $taxBreakdown = [];
            $invoice = null;
            if ($request->filled('invoice_id')) {
                $invoice = \App\Models\Invoice::with('items.taxes')->find($request->input('invoice_id'));
                if ($invoice) {
                    $invoiceNo = $invoice->invoice_no;
                    if ($invoice->business_id) {
                        $business = \App\Models\Business::find($invoice->business_id);
                    }
                    $taxBreakdown = $invoice->consolidatedTaxBreakdown();
                }
            }

            if (!$business) {
                $businessId = session('business_id') ?? (auth()->check() && auth()->user()->businesses()->count() > 0 ? auth()->user()->businesses()->first()->id : null);
                if ($businessId) {
                    $business = \App\Models\Business::find($businessId);
                }
            }

            // Calculate totals
            $subtotal = 0;
            $grossSubtotal = 0;
            $totalItemDiscount = 0;
            $cartItems = [];

            foreach ($cartData as $index => $item) {
                // Validate required fields
                if (!isset($item['name']) || !isset($item['price']) || !isset($item['qty'])) {
                    continue; // Skip invalid items
                }

                $price = floatval($item['price']);
                $qty = floatval($item['qty']);
                $discountSelectedType = $item['discount_selected_type'] ?? 'percent';
                $discountPercent = floatval($item['discount_percent'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);

                // Validate numeric values
                if ($price < 0 || $qty < 0 || $discountPercent < 0 || $discountAmount < 0) {
                    continue; // Skip items with negative values
                }

                $base = $price * $qty;
                $grossSubtotal += $base;
                $discount = $discountSelectedType === 'percent'
                    ? $base * ($discountPercent / 100)
                    : $discountAmount;

                // Ensure discount doesn't exceed base amount
                $discount = min($discount, $base);
                $totalItemDiscount += $discount;
                $total = max(0, $base - $discount);

                $productModel = \App\Models\Product::find($item['product_id'] ?? null);
                $categoryModel = \App\Models\Category::find($item['category_id'] ?? null);
                
                $cartItems[] = [
                    'name' => $item['name'],
                    'price' => $price,
                    'qty' => $qty,
                    'discount_selected_type' => $discountSelectedType,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                    'category_name' => $categoryModel ? $categoryModel->name : '',
                    'strength' => $productModel && $productModel->strength ? $productModel->strength->name : '',
                    'product_type' => $productModel && $productModel->type ? $productModel->type->name : ''
                ];

                $subtotal += $total;
            }

            // Calculate invoice discount
            $invoiceDiscount = 0;
            if ($invoiceDiscountType === 'percent') {
                $invoiceDiscount = $subtotal * ($invoiceDiscountValue / 100);
            } else {
                $invoiceDiscount = min($invoiceDiscountValue, $subtotal);
            }

            $grandTotal = max(0, $subtotal - $invoiceDiscount);

            // For a saved invoice (the only real path now — there is no
            // pre-save "print without saving" anymore), the DB record is
            // authoritative: it includes the server-computed additive Sale
            // Tax that the posted cart data never accounted for. Override
            // the cart-derived totals so the printed receipt always matches
            // what was actually charged/persisted.
            if ($invoice) {
                $grossSubtotal = (float) $invoice->subtotal;
                $totalItemDiscount = (float) $invoice->discount;
                $subtotal = (float) $invoice->total;
                $invoiceDiscount = (float) $invoice->invoice_discount_amount;
                $grandTotal = (float) $invoice->grand_total;
                $cashReceived = (float) $invoice->cash_received;
                $changeReturn = (float) $invoice->change_return;
            }

            return view('admin.pos.receipt-print', [
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'grossSubtotal' => $grossSubtotal,
                'totalItemDiscount' => $totalItemDiscount,
                'invoiceDiscountValue' => $invoiceDiscountValue,
                'invoiceDiscountType' => $invoiceDiscountType,
                'invoiceDiscount' => $invoiceDiscount,
                'grandTotal' => $grandTotal,
                'cashReceived' => $cashReceived,
                'changeReturn' => $changeReturn,
                'invoice_no' => $invoiceNo,
                'business' => $business,
                'taxBreakdown' => $taxBreakdown,
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Print receipt error: ' . $e->getMessage());

            // Return empty receipt with error message
            return view('admin.pos.receipt-print', [
                'cartItems' => [],
                'subtotal' => 0,
                'invoiceDiscountValue' => 0,
                'invoiceDiscountType' => 'amount',
                'invoiceDiscount' => 0,
                'grandTotal' => 0,
                'cashReceived' => $cashReceived,
                'changeReturn' => $changeReturn,
                'error' => 'Failed to generate receipt: ' . $e->getMessage(),
                'business' => null,
                'taxBreakdown' => [],
            ]);
        }
    }

    public function saveInvoice(Request $request)
    {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // -------------------------------
            // 1. CART DATA
            // -------------------------------
            $cartInput = $request->input('cart', []);
            $cartData = is_array($cartInput) ? $cartInput : json_decode($cartInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid cart data'], 400);
            }

            if (empty($cartData)) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            // -------------------------------
            // 1.5 CHECK STOCK BEFORE PROCEEDING VIA PRODUCT CONTROLLER LOGIC
            // -------------------------------
            $productRequests = [];
            foreach ($cartData as $item) {
                $productId = $item['id'];
                $catId = $item['category_id'] ?? null;
                $qty = (float)$item['qty'];
                
                [$baseCategoryId, $baseQty] = $this->purchaseController
                    ->calculateBaseStock($productId, $catId, $qty);
                    
                if (!isset($productRequests[$productId])) {
                    $productRequests[$productId] = [
                        'name' => $item['name'],
                        'category_id' => $catId, // Track at least one category to convert back to
                        'total_base_qty_requested' => 0,
                        'multiplier' => ($qty > 0) ? ($baseQty / $qty) : 1,
                    ];
                }
                $productRequests[$productId]['total_base_qty_requested'] += $baseQty;
            }

            foreach ($productRequests as $productId => $req) {
                // Call ProductController directly to get available stock respecting all rules
                $availableResult = $this->productController->getAvailableStockWithPriceGrouping(
                    $productId, 
                    $req['category_id'], 
                    ['per_batch' => [], 'per_price' => []] // Empty reserved because we are checking total cart state
                );
                
                $availableBase = $availableResult['total_base_qty'];
                    
                if (round($req['total_base_qty_requested'], 4) > round($availableBase, 4)) {
                    \Illuminate\Support\Facades\DB::rollBack();
                    
                    // Leverage ProductController to perform precise backward conversion to Category Qty
                    $parameters = \App\Models\ProductParameter::where('product_id', $productId)->get();
                    try {
                        $availableCategoryQty = floor($this->productController->convertFromBaseQuantityOptimized(
                            $productId,
                            $req['category_id'],
                            $availableBase,
                            $parameters
                        ));
                    } catch (\Exception $e) {
                        $availableCategoryQty = floor($availableBase / $req['multiplier']);
                    }
                    
                    return response()->json([
                        'error' => 'stock_exceeded',
                        'product_id' => $productId,
                        'product_name' => $req['name'],
                        'available_category_qty' => $availableCategoryQty
                    ], 400);
                }
            }

            // -------------------------------
            // 2. CREATE INVOICE
            // -------------------------------
            $invoice = \App\Models\Invoice::create([
                // Temporary unique placeholder — replaced below with the real
                // short local number or an FBR-issued number once we know the
                // row's own id / have FBR's response.
                'invoice_no'              => 'PENDING-' . \Illuminate\Support\Str::random(16),
                'subtotal'                => $request->subtotal ?? 0,
                'discount'                => 0,
                'total'                   => $request->total ?? 0,

                'invoice_discount_type'   => $request->invoice_discount_type,
                'invoice_discount_value'  => $request->invoice_discount_value,
                'invoice_discount_amount' => $request->invoice_discount_amount,
                'grand_total'             => $request->grand_total,
                'cash_received'           => $request->cash_received,
                'change_return'           => $request->change_return,
            ]);

            // -------------------------------
            // 2.1 ASSIGN THE REAL INVOICE NUMBER
            // -------------------------------
            $business = session('business_id') ? \App\Models\Business::find(session('business_id')) : null;

            if ($business && $business->isFbrInvoicing()) {
                try {
                    $fbrInvoiceNo = app(\App\Services\FbrInvoiceService::class)->generateInvoiceNumber($business, [
                        'grandTotal'  => $request->grand_total,
                        'subTotal'    => $request->subtotal,
                        'itemCount'   => count($cartData),
                        'invoiceDate' => now()->toDateTimeString(),
                    ]);
                    $invoice->invoice_no = $fbrInvoiceNo;
                    $invoice->save();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\DB::rollBack();

                    return response()->json([
                        'error' => 'fbr_failed',
                        'message' => 'Could not generate an FBR invoice number: ' . $e->getMessage(),
                    ], 502);
                }
            } else {
                $invoice->assignLocalInvoiceNo();
            }

            $subbTotol = 0;
            $totalItemDiscount = 0;

            // -------------------------------
            // 3. GROUP ITEMS BY PRICE GROUP FOR FIFO DEDUCTION
            // -------------------------------
            $itemsByPriceGroup = [];

            foreach ($cartData as $item) {
                $priceGroup = number_format((float)$item['price_group'], 2, '.', '');

                if (!isset($itemsByPriceGroup[$priceGroup])) {
                    $itemsByPriceGroup[$priceGroup] = [];
                }

                $itemsByPriceGroup[$priceGroup][] = $item;
            }

            // -------------------------------
            // 4. SAVE INVOICE ITEMS (existing logic)
            // -------------------------------
            // GST (from the Paid Unit Cost Price markup) is disclosure-only —
            // it never changes what the customer pays; it's persisted per
            // item on InvoiceItem.tax_amount only. Named Sale Taxes are real,
            // additive charges — they DO inflate the grand total, tracked
            // here so they can be rolled into Invoice.tax_amount below.
            $invoiceAdditiveTaxTotal = 0;

            foreach ($cartData as $item) {

                [$baseCategoryId, $baseQty] = $this->purchaseController
                    ->calculateBaseStock($item['id'], $item['category_id'], $item['qty']);

                $preferenceInfo = $this->productController->getSalePricePreference($item['id']);
                $baseCategoryPrice = $this->productController
                    ->calculateSalePrice($item['id'], $baseCategoryId, $preferenceInfo);

                $preference = $preferenceInfo['preference'];
                $includingTax = $preferenceInfo['including_tax'];

                $base = $item['price'] * $item['qty'];

                if ($item['discount_selected_type'] === 'percent') {
                    $discountAmount = $base * (($item['discount_percent'] ?? 0) / 100);
                } else {
                    $discountAmount = $item['discount_amount'] ?? 0;
                }

                $discountAmount = min($discountAmount, $base);
                $totalItemDiscount += $discountAmount;
                $rowTotal = $base - $discountAmount;

                $taxBuckets = $this->computeSaleTaxBreakdown($item['id'], $baseQty, $rowTotal);
                $itemGstAmount = array_sum(array_map(fn($b) => $b['tax_id'] === null ? $b['amount'] : 0, $taxBuckets));
                $itemAdditiveTaxAmount = array_sum(array_map(fn($b) => $b['tax_id'] !== null ? $b['amount'] : 0, $taxBuckets));

                $invoiceItem = \App\Models\InvoiceItem::create([
                    'invoice_id'      => $invoice->id,
                    'product_id'      => $item['id'],
                    'category_id'     => $item['category_id'],
                    'name'            => $item['name'],
                    'qty'             => $item['qty'],
                    'price'           => $item['price'],
                    'base_category_id'  => $baseCategoryId,
                    'base_quantity'      => $baseQty,
                    'base_category_price' => $baseCategoryPrice,
                    'sale_preference_slug' => $preference->slug,
                    'is_tax_included'      => $includingTax,

                    'discount_type'   => $item['discount_selected_type'],
                    'discount_value'  => $item['discount_selected_type'] === 'percent'
                        ? ($item['discount_percent'] ?? 0)
                        : ($item['discount_amount'] ?? 0),
                    'discount_amount' => $discountAmount,

                    'price_before_discount' => $item['price_before_discount'] ?? $item['price'],
                    'max_discount_percent'  => $item['max_discount_percent'] ?? 0,
                    'max_discount_amount'   => $item['max_discount_amount'] ?? 0,
                    'row_total'             => $rowTotal,
                    // GST only — disclosure, not part of what was charged.
                    // Additive Sale Tax amounts live in invoice_item_taxes
                    // (tax_id set) and are rolled into Invoice.tax_amount below.
                    'tax_amount'            => round($itemGstAmount, 2),
                ]);

                foreach ($taxBuckets as $bucket) {
                    if (round($bucket['amount'], 2) <= 0) {
                        continue;
                    }

                    \App\Models\InvoiceItemTax::create([
                        'invoice_item_id' => $invoiceItem->id,
                        'tax_id'          => $bucket['tax_id'],
                        'name'            => $bucket['name'],
                        'rate'            => round($bucket['rate'], 2),
                        'amount'          => round($bucket['amount'], 2),
                    ]);
                }

                $invoiceAdditiveTaxTotal += $itemAdditiveTaxAmount;
                $subbTotol += $base;
            }

            // -------------------------------
            // 5. UPDATE INVOICE TOTAL
            // -------------------------------
            // Grand total is computed authoritatively here (not trusted from
            // the client) because the additive Sale Tax total is only known
            // once we've resolved each item's purchase batch(es) server-side.
            $netTotal = $subbTotol - $totalItemDiscount;
            $invoiceDiscountAmount = (float) $invoice->invoice_discount_amount;
            $grandTotal = max(0, round($netTotal - $invoiceDiscountAmount + $invoiceAdditiveTaxTotal, 2));
            $cashReceived = (float) $invoice->cash_received;

            $invoice->update([
                'subtotal'     => $subbTotol,
                'discount'     => $totalItemDiscount,
                'total'        => $netTotal,
                // The amount that actually inflated grand_total (named Sale
                // Taxes only) — NOT the GST disclosure amount.
                'tax_amount'   => round($invoiceAdditiveTaxTotal, 2),
                'grand_total'  => $grandTotal,
                'change_return' => max(0, round($cashReceived - $grandTotal, 2)),
            ]);

            // -------------------------------
            // 6. DEDUCT STOCK (PRICE-GROUPED FIFO)
            // -------------------------------
            foreach ($itemsByPriceGroup as $priceKey => $groupItems) {
                $totalBaseQty = array_sum(array_column($groupItems, 'base_qty'));

                // Selected category (MUST MATCH allocation category)
                $selectedCategoryId = $groupItems[0]['category_id'] ?? null;

                $this->deductStockByPriceGroup(
                    $groupItems[0]['id'],        // product_id
                    $priceKey,                   // price group key
                    $totalBaseQty,               // total allocated base-qty for this price group
                    $selectedCategoryId          // used for matching sale price
                );
            }

            \App\Models\InvoiceHistory::create([
                'invoice_id' => $invoice->id,
                'action' => 'Created',
                'description' => 'Invoice created from POS.',
                'user_id' => auth()->id(),
            ]);

            \Illuminate\Support\Facades\DB::commit();
            return response()->json([
                'success'    => true,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'message'    => 'Invoice saved successfully.'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Log::error('Save invoice error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save invoice: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Disclosure-only tax breakdown for a sold line: how much of the amount
     * already being charged (row_total) is tax, split by tax name.
     *
     * This does NOT add anything on top of the price the customer pays —
     * unit_sale_price already is the final retail price. It informs the
     * customer/owner how much of that price is "GST" (the extra_paid_percent
     * markup captured at purchase time) or a named Sale Tax configured on
     * the purchase batch(es) this quantity is being sold from.
     *
     * Attribution is a read-only FIFO simulation over the same
     * BaseStockSalePrice rows (ordered by id, matching purchase order) that
     * deductStockByPriceGroup() will shortly deduct from for real — it does
     * NOT mutate stock itself. In the rare case a price group spans batches
     * out of strict id order (stock-wise-price preference falling back),
     * the attributed split can differ slightly from the exact batch(es)
     * physically decremented; the total taxable amount split is still
     * correct since it's driven by the actual sold row_total, not by stock.
     *
     * @return array<int, array{tax_id: ?int, name: string, rate: float, amount: float}>
     */
    private function computeSaleTaxBreakdown($productId, $baseQtyNeeded, $taxableAmount)
    {
        $buckets = []; // keyed by 'gst' or tax_id, => ['tax_id'=>, 'name'=>, 'amount'=>]

        if ($baseQtyNeeded <= 0 || $taxableAmount <= 0) {
            return [];
        }

        $stockRows = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->orderBy('id', 'asc')
            ->get();

        $remaining = (float) $baseQtyNeeded;
        $purchaseCache = [];

        foreach ($stockRows as $stockRow) {
            if (round($remaining, 4) <= 0) {
                break;
            }

            $qtyFromThisBatch = min($remaining, $stockRow->remaining_base_stock);
            if ($qtyFromThisBatch <= 0) {
                continue;
            }

            $purchaseId = $stockRow->purchase_id;
            if (!array_key_exists($purchaseId, $purchaseCache)) {
                $purchaseCache[$purchaseId] = Purchase::with('Saletaxes.tax')->find($purchaseId);
            }
            $purchase = $purchaseCache[$purchaseId];

            $proportion = $qtyFromThisBatch / $baseQtyNeeded;
            $chunkAmount = $taxableAmount * $proportion;

            if ($purchase && $purchase->extra_paid_percent > 0) {
                if (!isset($buckets['gst'])) {
                    $buckets['gst'] = ['tax_id' => null, 'name' => 'GST', 'amount' => 0];
                }
                $buckets['gst']['amount'] += $chunkAmount * ($purchase->extra_paid_percent / 100);
            }

            if ($purchase) {
                foreach ($purchase->Saletaxes as $saleTax) {
                    $key = 'tax_' . $saleTax->tax_id;
                    if (!isset($buckets[$key])) {
                        $buckets[$key] = [
                            'tax_id' => $saleTax->tax_id,
                            'name'   => $saleTax->tax->name ?? 'Tax',
                            'amount' => 0,
                        ];
                    }
                    $buckets[$key]['amount'] += $chunkAmount * ($saleTax->tax_rate / 100);
                }
            }

            $remaining -= $qtyFromThisBatch;
        }

        foreach ($buckets as &$bucket) {
            $bucket['rate'] = $taxableAmount > 0 ? ($bucket['amount'] / $taxableAmount) * 100 : 0;
        }

        return array_values($buckets);
    }

    function deductStockByPriceGroup($productId, $priceKey, $totalBaseQty, $selectedCategoryId = null)
    {
        $remainingToDeduct = (float)$totalBaseQty;

        $stockRows = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', now())
            ->orderBy('id', 'asc')
            ->get();

        $preferenceInfo = $this->productController->getSalePricePreference($productId);
        $preferenceSlug = $preferenceInfo['preference']->slug ?? 'static-price';

        foreach ($stockRows as $stockRow) {
            if (round($remainingToDeduct, 4) <= 0) break;

            $targetCategoryId = $selectedCategoryId ?? $stockRow->base_category_id;

            $calculatedPrice = $this->productController
                ->calculateUnitPriceForStockRow($stockRow, $targetCategoryId);

            $calculatedKey = number_format((float)$calculatedPrice, 2, '.', '');

            if ($preferenceSlug !== 'stock-wise-price' || $calculatedKey === $priceKey) {
                $deduct = min($remainingToDeduct, $stockRow->remaining_base_stock);

                $stockRow->remaining_base_stock -= $deduct;
                $stockRow->save();

                $remainingToDeduct -= $deduct;
            }
        }

        // If the exact price group was exhausted but the total warehouse check validated success,
        // fallback to standard FIFO deduction to fulfill the sold cart and preserve inventory integrity.
        if (round($remainingToDeduct, 4) > 0 && $preferenceSlug === 'stock-wise-price') {
            foreach ($stockRows as $stockRow) {
                if (round($remainingToDeduct, 4) <= 0) break;
                if ($stockRow->remaining_base_stock > 0) {
                    $deduct = min($remainingToDeduct, $stockRow->remaining_base_stock);
                    $stockRow->remaining_base_stock -= $deduct;
                    $stockRow->save();
                    $remainingToDeduct -= $deduct;
                }
            }
        }

        if (round($remainingToDeduct, 4) > 0) {
            throw new \Exception("Could not deduct all stock for price group {$priceKey}");
        }

        $productStock = \App\Models\ProductStock::where('product_id', $productId)->first();
        if ($productStock) {
            $productStock->current_stock -= $totalBaseQty;
            $productStock->save();
        }
    }

    public function saveCartSession(Request $request)
    {
        $request->validate([
            'cart' => 'required|string'
        ]);

        try {
            $cartData = json_decode($request->cart, true);
            $request->session()->put('pos_cart', $cartData ?? []);
            
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Failed to save cart session'
            ]);
        }
    }
}
