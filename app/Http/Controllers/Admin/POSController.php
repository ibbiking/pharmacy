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
        $this->purchaseController = app(PurchaseController::class);
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

        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $settings = app(\App\Services\Catalogue\SalePreferenceService::class)->business($businessId);

        return \Inertia\Inertia::render('POS/Index', [
            'receipt' => [
                'name' => $business?->name ?? settings('app_name', 'Business'),
                'address' => $business?->address,
                'phone' => $business?->phone,
                'note' => $business?->note,
                'next_invoice_no' => $nextInvoiceNo,
                'fbr' => (bool) $business?->isFbrInvoicing(),
            ],
            'defaultLotMode' => $settings['lot_mode'],
            'currency' => currency_symbol(),
            'cashier' => $request->user()->name,
            'cartKey' => 'pos-cart:'.$businessId.':'.$request->user()->id,
        ]);
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

        if (!auth()->user()?->isSalesPersonIn((int) (session('impersonate_business_id') ?? session('business_id')))) {
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

    // printReceipt (receipt from browser-sent cart data) removed in Roadmap 5.7b: the till prints the SAVED invoice (receipt()).

    /**
     * Save & Print. All money and stock maths is done server-side by CheckoutService
     * (prices, discounts, taxes, lot allocation); the posted cart is only a request.
     */
    /**
     * Live POS quote (Roadmap 5.7a): the server prices the whole cart exactly as checkout will (CheckoutService::preview)
     * so the screen never shows a number the invoice won't have. Read-only; JSON in, JSON out.
     */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'lines' => ['present', 'array', 'max:200'],
            'invoice_discount_type' => ['nullable', 'in:amount,percent'],
            'invoice_discount_value' => ['nullable', 'numeric', 'min:0'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
        ]);
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $showIncluded = ! $request->user()->isSalesPersonIn($businessId); // included tax is owner-only (D3)

        try {
            return response()->json(app(CheckoutService::class)->preview($data['lines'], $data, auth()->id(), $businessId, $showIncluded));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Couldn’t price the cart. Please try again.'], 500);
        }
    }

    /**
     * Print a SAVED invoice from the till (Roadmap 5.7b, S-3: never from browser data). A cashier may print a receipt
     * they rang up in the last 15 minutes; older ones need "View invoices" (reprint).
     */
    public function receipt(Request $request, $invoice)
    {
        $invoice = \App\Models\Invoice::query()->findOrFail($invoice);
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $own = \App\Models\InvoiceHistory::query()->where('invoice_id', $invoice->id)->where('action', 'Created')
            ->where('user_id', auth()->id())->where('created_at', '>=', now()->subMinutes(15))->exists();
        abort_unless($own || app(\App\Services\Access\PermissionService::class)->allows($request->user(), $businessId, 'pos.invoices.view'), 403,
            'Reprinting older receipts needs the “View invoices” permission.');

        return app(\App\Http\Controllers\Admin\InvoiceController::class)->printInvoice($invoice->invoice_no);
    }

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
            // Never send internals to the till; the full error is in the log.
            report($e);
            return response()->json(['error' => 'save_failed', 'message' => 'The sale could not be saved. Nothing was charged; please try again.'], 500);
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
