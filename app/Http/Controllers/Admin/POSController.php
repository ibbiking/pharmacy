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
use App\Services\Sales\CheckoutService;
use App\Services\Sales\Exceptions\InsufficientStockException;
use App\Services\Sales\Exceptions\SaleRuleViolation;
use App\Services\Sales\LotAllocator;
use App\Services\Sales\PackagingService;
use App\Services\Sales\SalePreferenceResolver;
use App\Services\Sales\SaleSettings;
use App\Services\Sales\SaleTaxCalculator;
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
     * Live tax preview for the POS cart (read-only). Uses the same lot allocation and tax rules as
     * checkout, so Grand Total / Change Return on screen match what will be saved:
     *  - "salesTaxes": named Sale Taxes ADDED on top (when prices exclude tax)
     *  - "gstTaxes": GST disclosure (never added)
     * Taxes included inside the price (decision D3) are not shown to cashiers; owners also get
     * "includedTaxes"/"includedTaxTotal".
     */
    public function cartTaxBreakdown(Request $request)
    {
        $cartInput = $request->input('cart', []);
        $cartData = is_array($cartInput) ? $cartInput : json_decode($cartInput, true);

        $empty = ['salesTaxes' => [], 'gstTaxes' => [], 'salesTaxTotal' => 0, 'gstTotal' => 0];
        if (!is_array($cartData)) {
            return response()->json($empty);
        }

        $packaging = app(PackagingService::class);
        $resolver = app(SalePreferenceResolver::class);
        $allocator = app(LotAllocator::class);
        $taxes = app(SaleTaxCalculator::class);

        $groups = ['sales' => [], 'gst' => [], 'included' => []];
        $remaining = [];
        $lotsByProduct = [];

        foreach ($cartData as $item) {
            $productId = (int) ($item['product_id'] ?? $item['id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            if ($productId <= 0 || $qty <= 0 || $price <= 0) {
                continue;
            }

            try {
                $settings = $resolver->forProduct($productId);
                $categoryId = !empty($item['category_id']) ? (int) $item['category_id'] : $packaging->baseCategoryId($productId);
                [, $baseQty] = $packaging->toBase($productId, $categoryId, $qty);

                $mode = in_array($item['lot_selection_mode'] ?? null, SaleSettings::LOT_MODES, true)
                    ? $item['lot_selection_mode'] : $settings->lotMode;
                $lotsByProduct[$productId] ??= $allocator->sellableLots($productId, SaleSettings::LOT_FIFO);
                $lots = $allocator->sort($lotsByProduct[$productId], $mode);
                $allocations = $allocator->allocate($lots, $remaining, $baseQty,
                    !empty($item['base_stock_sale_price_id']) ? (int) $item['base_stock_sale_price_id'] : null);
            } catch (\Throwable $e) {
                continue; // preview only: an unavailable line simply contributes no tax
            }

            $base = $price * $qty;
            $discount = ($item['discount_selected_type'] ?? 'percent') === 'percent'
                ? $base * ((float) ($item['discount_percent'] ?? 0) / 100)
                : (float) ($item['discount_amount'] ?? 0);
            $rowTotal = max(0, $base - min($discount, $base));

            foreach ($taxes->breakdown($allocations, $rowTotal, $settings->includeTax) as $bucket) {
                if (round($bucket['amount'], 2) <= 0) {
                    continue;
                }
                $group = $bucket['tax_id'] === null ? 'gst' : ($bucket['is_included'] ? 'included' : 'sales');
                $key = $bucket['tax_id'] ?? 'gst';
                $groups[$group][$key] ??= ['tax_id' => $bucket['tax_id'], 'name' => $bucket['name'], 'amount' => 0, 'taxable_base' => 0];
                $groups[$group][$key]['amount'] += $bucket['amount'];
                $groups[$group][$key]['taxable_base'] += $rowTotal;
            }
        }

        $format = fn ($buckets) => array_map(function ($b) {
            $b['rate'] = round($b['taxable_base'] > 0 ? ($b['amount'] / $b['taxable_base']) * 100 : 0, 2);
            $b['amount'] = round($b['amount'], 2);
            unset($b['taxable_base']);
            return $b;
        }, array_values($buckets));

        $salesTaxes = $format($groups['sales']);
        $gstTaxes = $format($groups['gst']);

        $response = [
            'salesTaxes' => $salesTaxes,
            'gstTaxes' => $gstTaxes,
            'salesTaxTotal' => round(array_sum(array_column($salesTaxes, 'amount')), 2),
            'gstTotal' => round(array_sum(array_column($gstTaxes, 'amount')), 2),
        ];

        if (!auth()->user()?->hasRole('sales-person')) {
            $included = $format($groups['included']);
            $response['includedTaxes'] = $included;
            $response['includedTaxTotal'] = round(array_sum(array_column($included, 'amount')), 2);
        }

        return response()->json($response);
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

    /**
     * Save & Print. All money and stock maths is done server-side by CheckoutService
     * (prices, discounts, taxes, lot allocation); the posted cart is only a request.
     */
    public function saveInvoice(Request $request)
    {
        $cartInput = $request->input('cart', []);
        $cartData = is_array($cartInput) ? $cartInput : json_decode($cartInput, true);

        if (!is_array($cartData)) {
            return response()->json(['error' => 'Invalid cart data'], 400);
        }
        if (empty($cartData)) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));

        try {
            $result = app(CheckoutService::class)->checkout(
                array_values($cartData),
                [
                    'invoice_discount_type' => $request->input('invoice_discount_type'),
                    'invoice_discount_value' => $request->input('invoice_discount_value'),
                    'cash_received' => $request->input('cash_received'),
                    'grand_total' => $request->input('grand_total'),
                    'subtotal' => $request->input('subtotal'),
                ],
                auth()->id(),
                $businessId
            );
        } catch (InsufficientStockException $e) {
            $line = collect($cartData)->first(fn ($l) => (int) ($l['product_id'] ?? $l['id'] ?? 0) === $e->productId) ?? [];
            $categoryId = !empty($line['category_id']) ? (int) $line['category_id'] : null;
            try {
                $availableCategoryQty = floor(app(PackagingService::class)->fromBase($e->productId, $categoryId, $e->availableBaseQty) + 1e-9);
            } catch (\Throwable $ignored) {
                $availableCategoryQty = floor($e->availableBaseQty);
            }

            return response()->json([
                'error' => 'stock_exceeded',
                'product_id' => $e->productId,
                'product_name' => $line['name'] ?? '',
                'available_category_qty' => $availableCategoryQty,
            ], 400);
        } catch (SaleRuleViolation $e) {
            return response()->json([
                'error' => $e->errorCode,
                'message' => $e->getMessage(),
                'context' => $e->context,
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'invalid_line', 'message' => 'A product in the cart was not found.'], 422);
        } catch (\App\Services\Sales\Exceptions\FbrInvoicingFailed $e) {
            return response()->json([
                'error' => 'fbr_failed',
                'message' => 'Could not generate an FBR invoice number: ' . $e->getMessage(),
            ], 502);
        } catch (\Throwable $e) {
            \Log::error('Save invoice error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Failed to save invoice: ' . $e->getMessage()], 500);
        }

        // Release this cart's stock hold now that it is a real invoice.
        \App\Models\PosCartReservation::releaseForUser(auth()->id(), $businessId);
        $request->session()->forget('pos_cart');

        return response()->json([
            'success' => true,
            'invoice_id' => $result->invoice->id,
            'invoice_no' => $result->invoice->invoice_no,
            'grand_total' => (float) $result->invoice->grand_total,
            'price_overrides' => $result->priceOverrides,
            'message' => 'Invoice saved successfully.',
        ]);
    }


    public function saveCartSession(Request $request)
    {
        $request->validate([
            'cart' => 'required|string'
        ]);

        try {
            $cartData = json_decode($request->cart, true) ?? [];
            $request->session()->put('pos_cart', $cartData);

            // Mirror this session's cart into the shared reservation table so
            // other sales-persons in the same business see this stock as
            // held. Full replace on every sync — an emptied cart naturally
            // ends up with zero reservation rows, no separate "release"
            // call needed for normal edits/removals.
            //
            // base_qty is deliberately RECOMPUTED here from quantity +
            // category_id via the same authoritative conversion used
            // everywhere else (calculateBaseStock), rather than trusted from
            // the posted cart line — the client's own base_qty is a value
            // accumulated across several JS operations (add, merge,
            // qty-edit) and can drift from the true quantity×multiplier
            // relationship. Reservations must count stock from exactly one
            // place, so this is the only spot they're derived from.
            $reservationLines = [];
            foreach ($cartData as $line) {
                if (!isset($line['product_id'], $line['quantity'])) {
                    continue;
                }
                $qty = (float) $line['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                [, $baseQty] = $this->purchaseController->calculateBaseStock(
                    $line['product_id'],
                    $line['category_id'] ?? null,
                    $qty
                );

                $reservationLines[] = [
                    'product_id' => $line['product_id'],
                    'category_id' => $line['category_id'] ?? null,
                    'base_stock_sale_price_id' => $line['base_stock_sale_price_id'] ?? null,
                    'price_group' => $line['price_group'] ?? null,
                    'base_qty' => $baseQty,
                    'quantity' => $qty,
                ];
            }
            \App\Models\PosCartReservation::syncForUser(auth()->id(), $reservationLines);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error', 
                'message' => 'Failed to save cart session'
            ]);
        }
    }
}
