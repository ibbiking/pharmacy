<?php

namespace App\Services\Sales;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemLot;
use App\Models\InvoiceItemTax;
use App\Models\PosCartReservation;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Services\Sales\Exceptions\InsufficientStockException;
use App\Services\Sales\Exceptions\SaleRuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * POS checkout. The server is the authority for every number (Roadmap step 2):
 *
 *  - quantity → base units via PackagingService (the browser's `base_qty` is ignored, bug B2)
 *  - unit price recomputed from the sale-price preference (bug P1); a different price from the
 *    browser is only accepted when price changes are allowed and it is not below the floor (D2),
 *    and is then recorded as an override
 *  - line and invoice discounts recomputed and checked against the caps (D2)
 *  - stock taken lot by lot, FEFO or FIFO per line (D1), with the lots locked for the transaction;
 *    each product's lots are allocated separately (bug B1: lines were grouped by price only)
 *  - tax attributed to the lots actually sold, never to expired lots (bug B4); named Sale Tax is
 *    inside the price or added on top per the "including tax" preference (D3 / bug P3)
 *  - every consumed lot is written to invoice_item_lots (returns + real cost of goods sold, R1/R2)
 */
class CheckoutService
{
    /** Prices are money with 2 decimals; anything closer than this counts as "the same price". */
    private const PRICE_TOLERANCE = 0.005;

    /** @var array<int, array{0: float, 1: float}> purchaseId => [unit cost, unit purchase tax] (per checkout) */
    private array $lotCostCache = [];

    /** The cashier's role rules for this checkout (Roadmap 4i-4); the strictest of role/business/product wins. */
    private bool $roleCanDiscount = true;
    private bool $roleCanChangePrice = true;
    private ?float $roleMaxDiscountPercent = null;

    public function __construct(
        private readonly PackagingService $packaging,
        private readonly SalePreferenceResolver $preferences,
        private readonly LotAllocator $lots,
        private readonly SalePriceCalculator $prices,
        private readonly SaleTaxCalculator $taxes,
    ) {
    }

    /**
     * @param  array  $cartLines  POS cart lines (id/product_id, category_id, qty, price, discount_*, base_stock_sale_price_id, lot_selection_mode)
     * @param  array  $invoiceInput  invoice_discount_type ('percent'|'amount'), invoice_discount_value, cash_received
     *
     * @throws InsufficientStockException|SaleRuleViolation
     */
    public function checkout(array $cartLines, array $invoiceInput, ?int $userId, int $businessId): CheckoutResult
    {
        if (empty($cartLines)) {
            throw new SaleRuleViolation(SaleRuleViolation::INVALID_LINE, 'Cart is empty.');
        }

        $this->lotCostCache = [];
        $this->loadRoleRules($userId, $businessId);

        $result = DB::transaction(function () use ($cartLines, $invoiceInput, $userId, $businessId) {
            $lines = array_map(fn ($line, $i) => $this->normaliseLine($line, $i), $cartLines, array_keys($cartLines));

            // ---- 1. Lock every sellable lot of every product in the cart, check total availability.
            $productIds = array_values(array_unique(array_column($lines, 'product_id')));
            sort($productIds); // consistent lock order across concurrent checkouts

            $products = [];
            $settings = [];
            $lockedLots = [];
            $remaining = []; // lotId => remaining base qty (in-memory ledger shared by all lines)

            foreach ($productIds as $productId) {
                $products[$productId] = Product::query()->findOrFail($productId);
                $settings[$productId] = $this->preferences->forProduct($products[$productId]);
                $lockedLots[$productId] = $this->lots->sellableLots($productId, SaleSettings::LOT_FIFO, lock: true);
                foreach ($lockedLots[$productId] as $lot) {
                    $remaining[$lot->id] = (float) $lot->remaining_base_stock;
                }
            }

            foreach ($productIds as $productId) {
                $requested = array_sum(array_column(array_filter($lines, fn ($l) => $l['product_id'] === $productId), 'base_qty'));
                $onHand = $lockedLots[$productId]->sum(fn ($lot) => (float) $lot->remaining_base_stock);
                $heldByOthers = $this->reservedByOthers($productId, $userId);
                $available = max(0, $onHand - $heldByOthers);

                if (round($requested, 4) > round($available, 4)) {
                    throw new InsufficientStockException($productId, $requested, $available);
                }
            }

            // ---- 2. Create the invoice shell and its number.
            $invoice = Invoice::create([
                'invoice_no' => 'PENDING-'.\Illuminate\Support\Str::random(16),
                'subtotal' => 0, 'discount' => 0, 'total' => 0, 'grand_total' => 0,
                'invoice_discount_type' => $invoiceInput['invoice_discount_type'] ?? null,
                'invoice_discount_value' => (float) ($invoiceInput['invoice_discount_value'] ?? 0),
                'invoice_discount_amount' => 0,
                'cash_received' => (float) ($invoiceInput['cash_received'] ?? 0),
                'change_return' => 0,
            ]);
            $this->assignNumber($invoice, $businessId, $invoiceInput, count($lines));

            // ---- 3. Lines: price, discount, lots, taxes.
            $subtotal = 0.0;
            $lineDiscounts = 0.0;
            $additiveTax = 0.0;
            $includedTax = 0.0;
            $overrides = [];
            $stockTaken = []; // productId => base qty

            foreach ($lines as $line) {
                $productId = $line['product_id'];
                $setting = $settings[$productId];
                $lotMode = $line['lot_selection_mode'] ?? $setting->lotMode;
                $orderedLots = $this->lots->sort($lockedLots[$productId], $lotMode);

                $allocations = $this->lots->allocate($orderedLots, $remaining, $line['base_qty'], $line['lot_id']);

                [
                    'price_lot' => $priceLot, 'server_price' => $serverPrice, 'charged_price' => $chargedPrice, 'overridden' => $overridden,
                    'line_base' => $lineBase, 'discount' => $discount, 'row_total' => $rowTotal, 'tax_buckets' => $taxBuckets,
                    'gst' => $lineGst, 'additive' => $lineAdditive, 'included' => $lineIncluded,
                ] = $this->priceLine($line, $setting, $products[$productId], $allocations);

                $item = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $productId,
                    'category_id' => $line['category_id'],
                    'name' => $line['name'] ?: $products[$productId]->product_name,
                    'qty' => $line['qty'],
                    'price' => $chargedPrice,
                    'server_unit_price' => $serverPrice,
                    'price_overridden' => $overridden,
                    'base_category_id' => $line['base_category_id'],
                    'base_quantity' => $line['base_qty'],
                    'base_category_price' => $priceLot
                        ? $this->prices->lotUnitPrice($priceLot, $line['base_category_id'], $setting->includeTax)
                        : $this->prices->unitPrice($productId, $line['base_category_id'], $setting),
                    'sale_preference_slug' => $setting->priceMode,
                    'lot_selection_mode' => $lotMode,
                    'is_tax_included' => $setting->includeTax,
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_type'] === 'percent' ? $line['discount_percent'] : $line['discount_amount'],
                    'discount_amount' => $discount,
                    'price_before_discount' => $chargedPrice,
                    'max_discount_percent' => $setting->productMaxDiscountPercent ?? 0,
                    'max_discount_amount' => $setting->productMaxDiscountAmount ?? 0,
                    'row_total' => $rowTotal,
                    'tax_amount' => round($lineGst, 2),          // GST disclosure only
                    'included_tax_amount' => round($lineIncluded, 2), // owner-only
                ]);

                foreach ($taxBuckets as $bucket) {
                    if (round($bucket['amount'], 2) <= 0) {
                        continue;
                    }
                    InvoiceItemTax::create([
                        'invoice_item_id' => $item->id,
                        'tax_id' => $bucket['tax_id'],
                        'name' => $bucket['name'],
                        'rate' => round($bucket['rate'], 2),
                        'amount' => round($bucket['amount'], 2),
                        'is_included' => $bucket['is_included'],
                    ]);
                }

                foreach ($allocations as $allocation) {
                    $lot = $allocation['lot'];
                    [$unitCost, $unitPurchaseTax] = $this->lotCost((int) $lot->purchase_id);

                    InvoiceItemLot::create([
                        'business_id' => $businessId,
                        'invoice_item_id' => $item->id,
                        'base_stock_sale_price_id' => $lot->id,
                        'purchase_id' => $lot->purchase_id,
                        'base_qty' => $allocation['qty'],
                        'base_unit_cost' => $unitCost,
                        'base_unit_purchase_tax' => $unitPurchaseTax,
                        'base_unit_sale_price' => (float) $lot->base_category_unit_sale_price,
                    ]);
                }

                if ($overridden) {
                    $overrides[] = ['item_id' => $item->id, 'name' => $item->name, 'server' => $serverPrice, 'charged' => $chargedPrice];
                }

                $subtotal += $lineBase;
                $lineDiscounts += $discount;
                $additiveTax += $lineAdditive;
                $includedTax += $lineIncluded;
                $stockTaken[$productId] = ($stockTaken[$productId] ?? 0) + $line['base_qty'];
            }

            // ---- 4. Deduct stock from the locked lots (the ledger holds the new remaining values).
            foreach ($lockedLots as $productLots) {
                foreach ($productLots as $lot) {
                    $newRemaining = round($remaining[$lot->id], 4);
                    if (abs($newRemaining - (float) $lot->remaining_base_stock) > 0.00001) {
                        $lot->remaining_base_stock = $newRemaining;
                        $lot->save();
                    }
                }
            }
            foreach ($stockTaken as $productId => $baseQty) {
                $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
                if ($stock) {
                    $stock->current_stock = (float) $stock->current_stock - $baseQty;
                    $stock->save();
                }
            }

            // ---- 5. Invoice totals (server computed).
            $net = round($subtotal - $lineDiscounts, 2);
            $invoiceDiscount = $this->resolveInvoiceDiscount($invoiceInput, $net, $businessId);
            $grandTotal = max(0, round($net - $invoiceDiscount + $additiveTax, 2));
            $cash = (float) ($invoiceInput['cash_received'] ?? 0);
            if ($cash + self::PRICE_TOLERANCE < $grandTotal) {
                // Cash sales only: the cash taken must cover the bill (was checked in the browser only).
                throw new SaleRuleViolation(SaleRuleViolation::CASH_SHORT,
                    'Cash received ('.number_format($cash, 2).') is less than the total ('.number_format($grandTotal, 2).').',
                    ['grand_total' => $grandTotal, 'cash_received' => $cash]);
            }

            $invoice->update([
                'subtotal' => round($subtotal, 2),
                'discount' => round($lineDiscounts, 2),
                'total' => $net,
                'invoice_discount_amount' => $invoiceDiscount,
                'tax_amount' => round($additiveTax, 2),       // additive Sale Tax only
                'included_tax_amount' => round($includedTax, 2), // owner-only
                'grand_total' => $grandTotal,
                'change_return' => max(0, round($cash - $grandTotal, 2)),
            ]);

            InvoiceHistory::create([
                'invoice_id' => $invoice->id,
                'action' => 'Created',
                'description' => 'Invoice created from POS.',
                'user_id' => $userId,
            ]);
            foreach ($overrides as $o) {
                $description = sprintf('%s: system price %.2f, charged %.2f.', $o['name'], $o['server'], $o['charged']);
                InvoiceHistory::create([
                    'invoice_id' => $invoice->id,
                    'action' => 'Price Override',
                    'description' => $description,
                    'user_id' => $userId,
                ]);
                app(\App\Services\Audit\AuditLogger::class)->record(
                    'price_override',
                    InvoiceItem::find($o['item_id']),
                    ['price' => ['from' => $o['server'], 'to' => $o['charged']]],
                    "Invoice {$invoice->invoice_no}: {$description}",
                );
            }

            return new CheckoutResult($invoice->fresh(), $overrides);
        });

        // Roadmap 13c: after the sale is committed, alert this business's people about products that just fell to their
        // alert level. Never lets a notification problem undo or fail a saved sale.
        try {
            app(\App\Services\Stock\StockAlertService::class)->afterSale($result->invoice, $businessId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * Price one line exactly as checkout does: system price (stock-wise lines from the lot they sell from), allowed
     * override, capped discount, taxes of the lots sold. Shared by checkout() and preview() so screen and invoice match.
     *
     * @throws SaleRuleViolation
     */
    private function priceLine(array $line, SaleSettings $setting, Product $product, array $allocations, ?float $forcePrice = null, bool $skipDiscount = false): array
    {
        $priceLot = $setting->priceMode === SaleSettings::PRICE_STOCK_WISE && $allocations ? $allocations[0]['lot'] : null;
        $serverPrice = $this->prices->unitPrice($line['product_id'], $line['category_id'], $setting, $priceLot);

        $chargedPrice = $forcePrice ?? $this->resolveChargedPrice($line, $serverPrice, $setting, $product);
        $overridden = abs($chargedPrice - $serverPrice) > self::PRICE_TOLERANCE;

        $lineBase = round($chargedPrice * $line['qty'], 2);
        $discount = $skipDiscount ? 0.0 : $this->resolveLineDiscount($line, $lineBase, $setting, $product);
        $rowTotal = round($lineBase - $discount, 2);

        $taxBuckets = $this->taxes->breakdown($allocations, $rowTotal, $setting->includeTax);

        return [
            'price_lot' => $priceLot, 'server_price' => $serverPrice, 'charged_price' => $chargedPrice, 'overridden' => $overridden,
            'line_base' => $lineBase, 'discount' => $discount, 'row_total' => $rowTotal, 'tax_buckets' => $taxBuckets,
            'gst' => SaleTaxCalculator::gstTotal($taxBuckets), 'additive' => SaleTaxCalculator::additiveTotal($taxBuckets),
            'included' => SaleTaxCalculator::includedTotal($taxBuckets),
        ];
    }

    /**
     * Read-only preview of a checkout (Roadmap 5.7a): the same pricing, caps, lot allocation and taxes as checkout(),
     * nothing written or locked. Instead of stopping at the first problem it reports every problem per line (and for
     * the invoice), falling back to the system price / no discount for the offending part so the rest stays visible.
     * `can_checkout` is true only when checkout() would accept this cart.
     */
    public function preview(array $cartLines, array $invoiceInput, ?int $userId, int $businessId, bool $showIncluded = false): array
    {
        $this->lotCostCache = [];
        $this->loadRoleRules($userId, $businessId);
        $issues = [];
        $lines = [];
        $useSystemPrice = [];
        foreach (array_values($cartLines) as $i => $raw) {
            $raw = is_array($raw) ? $raw : [];
            if (! isset($raw['price']) || $raw['price'] === '') {
                $useSystemPrice[$i] = true; // no price typed: the line sells at the system price
                $raw['price'] = 0;
            }
            try {
                $lines[] = $this->normaliseLine($raw, $i);
            } catch (SaleRuleViolation $e) {
                $issues[] = ['line' => $i, 'code' => $e->errorCode, 'message' => $e->getMessage()];
            } catch (\InvalidArgumentException $e) { // unit not in the product's packaging
                $issues[] = ['line' => $i, 'code' => SaleRuleViolation::INVALID_LINE, 'message' => 'Pick one of this product’s units.'];
            }
        }

        $products = [];
        $settings = [];
        $lots = [];
        $remaining = [];
        $available = [];
        foreach (array_unique(array_column($lines, 'product_id')) as $productId) {
            $product = Product::query()->find($productId);
            if (! $product) {
                continue;
            }
            $products[$productId] = $product;
            $settings[$productId] = $this->preferences->forProduct($product);
            $lots[$productId] = $this->lots->sellableLots($productId, SaleSettings::LOT_FIFO);
            foreach ($lots[$productId] as $lot) {
                $remaining[$lot->id] = (float) $lot->remaining_base_stock;
            }
            $available[$productId] = max(0, $lots[$productId]->sum(fn ($l) => (float) $l->remaining_base_stock) - $this->reservedByOthers($productId, $userId));
        }

        $out = [];
        $subtotal = $lineDiscounts = $additiveTax = $includedTax = 0.0;
        $taxRows = [];
        foreach ($lines as $line) {
            $productId = $line['product_id'];
            $index = $line['index'];
            if (! isset($products[$productId])) {
                $issues[] = ['line' => $index, 'code' => SaleRuleViolation::INVALID_LINE, 'message' => 'This product is no longer available.'];
                continue;
            }
            $setting = $settings[$productId];
            $product = $products[$productId];
            $lotMode = $line['lot_selection_mode'] ?? $setting->lotMode;

            // Stock: what this cashier can still take (other cashiers' carts hold theirs).
            $canTake = min($line['base_qty'], $available[$productId]);
            $available[$productId] -= $canTake;
            $allocations = [];
            if ($canTake > 0) {
                try {
                    $allocations = $this->lots->allocate($this->lots->sort($lots[$productId], $lotMode), $remaining, $canTake, $line['lot_id']);
                } catch (InsufficientStockException) {
                    $allocations = [];
                }
            }
            if ($canTake + 0.00001 < $line['base_qty']) {
                $inUnit = floor($this->packaging->fromBase($productId, $line['category_id'], $canTake) * 100 + 1e-6) / 100;
                $issues[] = ['line' => $index, 'code' => 'stock_exceeded', 'message' => $canTake > 0
                    ? "Only {$inUnit} left of {$product->product_name} in this unit."
                    : "{$product->product_name} is out of stock.", 'available' => $inUnit];
            }

            $lineIssues = [];
            $system = $this->prices->unitPrice($productId, $line['category_id'], $setting,
                $setting->priceMode === SaleSettings::PRICE_STOCK_WISE && $allocations ? $allocations[0]['lot'] : null);
            if (isset($useSystemPrice[$index])) {
                $line['price'] = round($system, 2);
            }
            try {
                $priced = $this->priceLine($line, $setting, $product, $allocations);
            } catch (SaleRuleViolation $e) {
                $lineIssues[] = ['line' => $index, 'code' => $e->errorCode, 'message' => $e->getMessage()] + array_intersect_key($e->context, array_flip(['server_price', 'minimum_price', 'max_percent', 'max_amount']));
                $priceBad = in_array($e->errorCode, [SaleRuleViolation::PRICE_CHANGE_NOT_ALLOWED, SaleRuleViolation::PRICE_BELOW_MINIMUM, SaleRuleViolation::INVALID_LINE], true);
                try {
                    // Show the line with the system price (if the price was refused) and without the refused discount.
                    $priced = $this->priceLine($line, $setting, $product, $allocations, $priceBad ? $system : null, skipDiscount: ! $priceBad);
                } catch (SaleRuleViolation $e2) {
                    $lineIssues[] = ['line' => $index, 'code' => $e2->errorCode, 'message' => $e2->getMessage()];
                    $priced = $this->priceLine($line, $setting, $product, $allocations, $system, skipDiscount: true);
                }
            }
            array_push($issues, ...$lineIssues);

            foreach ($priced['tax_buckets'] as $b) {
                if (round($b['amount'], 2) <= 0 || ($b['is_included'] && ! $showIncluded)) {
                    continue;
                }
                $kind = $b['tax_id'] === null ? 'gst' : ($b['is_included'] ? 'included' : 'additive');
                $key = $kind.':'.($b['tax_id'] ?? 'gst').':'.$b['name'];
                $taxRows[$key] ??= ['kind' => $kind, 'tax_id' => $b['tax_id'], 'name' => $b['name'], 'amount' => 0.0, 'taxable' => 0.0];
                $taxRows[$key]['amount'] += $b['amount'];
                $taxRows[$key]['taxable'] += $b['rate'] > 0 ? $b['amount'] * 100 / $b['rate'] : 0;
            }

            $out[] = [
                'line' => $index, 'product_id' => $productId, 'category_id' => $line['category_id'], 'qty' => $line['qty'],
                'base_qty' => $line['base_qty'], 'lot_mode' => $lotMode,
                'system_price' => round($priced['server_price'], 2), 'price' => round($priced['charged_price'], 2), 'overridden' => $priced['overridden'],
                'line_base' => $priced['line_base'], 'discount' => $priced['discount'], 'row_total' => $priced['row_total'],
                'gst' => round($priced['gst'], 2), 'additive_tax' => round($priced['additive'], 2), 'included_tax' => $showIncluded ? round($priced['included'], 2) : null,
                'lots' => array_map(fn ($a) => [
                    'id' => $a['lot']->id, 'qty' => round($a['qty'], 4), 'batch' => $a['lot']->purchase?->batch_no,
                    'expiry' => $a['lot']->expiry_date ? \Illuminate\Support\Carbon::parse($a['lot']->expiry_date)->toDateString() : null,
                ], $allocations),
                'rules' => [
                    'can_change_price' => $this->roleCanChangePrice && $setting->allowPriceChange,
                    'min_price' => $setting->minPricePercent !== null ? round($priced['server_price'] * $setting->minPricePercent / 100, 2) : null,
                    'can_discount' => $this->roleCanDiscount,
                    'max_discount_percent' => $this->minNullable($this->minNullable($setting->productMaxDiscountPercent, $setting->maxLineDiscountPercent), $this->roleMaxDiscountPercent),
                    'max_discount_amount' => $setting->productMaxDiscountAmount,
                ],
            ];
            $subtotal += $priced['line_base'];
            $lineDiscounts += $priced['discount'];
            $additiveTax += $priced['additive'];
            $includedTax += $priced['included'];
        }

        $net = round($subtotal - $lineDiscounts, 2);
        try {
            $invoiceDiscount = $this->resolveInvoiceDiscount($invoiceInput, $net, $businessId);
        } catch (SaleRuleViolation $e) {
            $issues[] = ['line' => null, 'code' => $e->errorCode, 'message' => $e->getMessage()] + array_intersect_key($e->context, array_flip(['max_percent']));
            $invoiceDiscount = 0.0;
        }
        $grandTotal = max(0, round($net - $invoiceDiscount + $additiveTax, 2));
        $cash = (float) ($invoiceInput['cash_received'] ?? 0);

        return [
            'lines' => $out,
            'totals' => [
                'subtotal' => round($subtotal, 2), 'line_discounts' => round($lineDiscounts, 2), 'net' => $net,
                'invoice_discount' => $invoiceDiscount, 'additive_tax' => round($additiveTax, 2),
                'included_tax' => $showIncluded ? round($includedTax, 2) : null,
                'grand_total' => $grandTotal, 'cash_received' => $cash, 'change' => max(0, round($cash - $grandTotal, 2)),
                'cash_short' => $cash + self::PRICE_TOLERANCE < $grandTotal,
            ],
            'taxes' => array_values(array_map(fn ($t) => $t + ['rate' => $t['taxable'] > 0 ? round($t['amount'] / $t['taxable'] * 100, 2) : 0.0, 'amount' => round($t['amount'], 2)], $taxRows)),
            'invoice' => [
                'max_discount_percent' => $this->minNullable($this->preferences->maxInvoiceDiscountPercent($businessId), $this->roleMaxDiscountPercent),
                'can_discount' => $this->roleCanDiscount,
            ],
            'issues' => array_values($issues),
            'can_checkout' => $issues === [] && $out !== [],
        ];
    }

    private function normaliseLine(array $line, int $index): array
    {
        $productId = (int) ($line['product_id'] ?? $line['id'] ?? 0);
        $qty = (float) ($line['qty'] ?? $line['quantity'] ?? 0);
        $categoryId = isset($line['category_id']) && $line['category_id'] !== '' && $line['category_id'] !== null
            ? (int) $line['category_id'] : null;

        if ($productId <= 0 || $qty <= 0 || ! array_key_exists('price', $line) || ! is_numeric($line['price'])) {
            throw new SaleRuleViolation(SaleRuleViolation::INVALID_LINE, 'Invalid cart line #'.($index + 1).'.', ['line' => $index]);
        }

        $categoryId ??= $this->packaging->baseCategoryId($productId);
        [$baseCategoryId, $baseQty] = $this->packaging->toBase($productId, $categoryId, $qty);

        $mode = $line['lot_selection_mode'] ?? null;
        $discountType = ($line['discount_selected_type'] ?? $line['discount_type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';

        return [
            'index' => $index,
            'product_id' => $productId,
            'name' => (string) ($line['name'] ?? ''),
            'category_id' => $categoryId,
            'qty' => $qty,
            'base_category_id' => $baseCategoryId,
            'base_qty' => $baseQty,
            'price' => round((float) $line['price'], 4),
            'lot_id' => ! empty($line['base_stock_sale_price_id']) ? (int) $line['base_stock_sale_price_id'] : null,
            'lot_selection_mode' => in_array($mode, SaleSettings::LOT_MODES, true) ? $mode : null,
            'discount_type' => $discountType,
            'discount_percent' => max(0, (float) ($line['discount_percent'] ?? 0)),
            'discount_amount' => max(0, (float) ($line['discount_amount'] ?? 0)),
        ];
    }

    private function resolveChargedPrice(array $line, float $serverPrice, SaleSettings $setting, Product $product): float
    {
        $requested = $line['price'];

        if (abs($requested - $serverPrice) <= self::PRICE_TOLERANCE) {
            return $serverPrice;
        }

        if (! $this->roleCanChangePrice) {
            throw new SaleRuleViolation(
                SaleRuleViolation::PRICE_CHANGE_NOT_ALLOWED,
                "Your role cannot change prices. System price for {$product->product_name}: ".number_format($serverPrice, 2).'.',
                ['line' => $line['index'], 'product_id' => $product->id, 'server_price' => $serverPrice, 'requested_price' => $requested, 'reason' => 'role'],
            );
        }

        if (! $setting->allowPriceChange) {
            throw new SaleRuleViolation(
                SaleRuleViolation::PRICE_CHANGE_NOT_ALLOWED,
                "Price changes are not allowed for {$product->product_name}. System price: ".number_format($serverPrice, 2).'.',
                ['line' => $line['index'], 'product_id' => $product->id, 'server_price' => $serverPrice, 'requested_price' => $requested],
            );
        }

        if ($setting->minPricePercent !== null) {
            $floor = round($serverPrice * $setting->minPricePercent / 100, 2);
            if ($requested + self::PRICE_TOLERANCE < $floor) {
                throw new SaleRuleViolation(
                    SaleRuleViolation::PRICE_BELOW_MINIMUM,
                    "Price for {$product->product_name} cannot be below ".number_format($floor, 2).'.',
                    ['line' => $line['index'], 'product_id' => $product->id, 'server_price' => $serverPrice, 'minimum_price' => $floor, 'requested_price' => $requested],
                );
            }
        }

        if ($requested < 0) {
            throw new SaleRuleViolation(SaleRuleViolation::INVALID_LINE, 'Price cannot be negative.', ['line' => $line['index']]);
        }

        return round($requested, 2);
    }

    private function resolveLineDiscount(array $line, float $lineBase, SaleSettings $setting, Product $product): float
    {
        $requested = $line['discount_type'] === 'percent' ? $line['discount_percent'] : $line['discount_amount'];
        if ($requested > 0 && ! $this->roleCanDiscount) {
            throw new SaleRuleViolation(SaleRuleViolation::LINE_DISCOUNT_EXCEEDED, 'Your role cannot give discounts.',
                ['line' => $line['index'], 'product_id' => $product->id, 'reason' => 'role']);
        }
        if ($this->roleMaxDiscountPercent !== null) {
            $setting = new SaleSettings(
                $setting->priceMode, $setting->includeTax, $setting->lotMode, $setting->allowPriceChange, $setting->minPricePercent,
                $setting->productMaxDiscountPercent, $setting->productMaxDiscountAmount,
                $this->minNullable($setting->maxLineDiscountPercent, $this->roleMaxDiscountPercent),
                $setting->maxInvoiceDiscountPercent, $setting->priceModeSource,
            );
        }

        if ($line['discount_type'] === 'percent') {
            $percent = $line['discount_percent'];
            $cap = $this->minNullable($setting->productMaxDiscountPercent, $setting->maxLineDiscountPercent);
            if ($cap !== null && $percent > $cap + 0.0001) {
                throw new SaleRuleViolation(
                    SaleRuleViolation::LINE_DISCOUNT_EXCEEDED,
                    "Discount on {$product->product_name} cannot exceed {$cap}%.",
                    ['line' => $line['index'], 'product_id' => $product->id, 'max_percent' => $cap],
                );
            }
            $amount = $lineBase * min($percent, 100) / 100;
        } else {
            $amount = $line['discount_amount'];
            if ($setting->productMaxDiscountAmount !== null && $amount > $setting->productMaxDiscountAmount + 0.0001) {
                throw new SaleRuleViolation(
                    SaleRuleViolation::LINE_DISCOUNT_EXCEEDED,
                    "Discount on {$product->product_name} cannot exceed ".number_format($setting->productMaxDiscountAmount, 2).'.',
                    ['line' => $line['index'], 'product_id' => $product->id, 'max_amount' => $setting->productMaxDiscountAmount],
                );
            }
            if ($setting->maxLineDiscountPercent !== null && $lineBase > 0
                && ($amount / $lineBase * 100) > $setting->maxLineDiscountPercent + 0.0001) {
                throw new SaleRuleViolation(
                    SaleRuleViolation::LINE_DISCOUNT_EXCEEDED,
                    "Discount on {$product->product_name} cannot exceed {$setting->maxLineDiscountPercent}% of the line.",
                    ['line' => $line['index'], 'product_id' => $product->id, 'max_percent' => $setting->maxLineDiscountPercent],
                );
            }
        }

        return round(min($amount, $lineBase), 2);
    }

    private function resolveInvoiceDiscount(array $invoiceInput, float $net, int $businessId): float
    {
        $type = ($invoiceInput['invoice_discount_type'] ?? 'amount') === 'percent' ? 'percent' : 'amount';
        $value = max(0, (float) ($invoiceInput['invoice_discount_value'] ?? 0));

        if ($value <= 0 || $net <= 0) {
            return 0.0;
        }

        if (! $this->roleCanDiscount) {
            throw new SaleRuleViolation(SaleRuleViolation::INVOICE_DISCOUNT_EXCEEDED, 'Your role cannot give discounts.', ['reason' => 'role']);
        }

        $amount = $type === 'percent' ? $net * min($value, 100) / 100 : $value;
        $amount = round(min($amount, $net), 2);

        $cap = $this->minNullable($this->preferences->maxInvoiceDiscountPercent($businessId), $this->roleMaxDiscountPercent);
        if ($cap !== null && ($amount / $net * 100) > $cap + 0.0001) {
            throw new SaleRuleViolation(
                SaleRuleViolation::INVOICE_DISCOUNT_EXCEEDED,
                "Invoice discount cannot exceed {$cap}%.",
                ['max_percent' => $cap],
            );
        }

        return $amount;
    }

    private function assignNumber(Invoice $invoice, int $businessId, array $invoiceInput, int $lineCount): void
    {
        $business = Business::find($businessId);

        if ($business && $business->isFbrInvoicing()) {
            // Stub until Roadmap step 6 (docs/FBR_INTEGRATION.md). Failing here rolls the sale back.
            try {
                $invoice->invoice_no = app(\App\Services\FbrInvoiceService::class)->generateInvoiceNumber($business, [
                    'grandTotal' => $invoiceInput['grand_total'] ?? null,
                    'subTotal' => $invoiceInput['subtotal'] ?? null,
                    'itemCount' => $lineCount,
                    'invoiceDate' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $e) {
                throw new \App\Services\Sales\Exceptions\FbrInvoicingFailed($e->getMessage(), 0, $e);
            }
            $invoice->save();

            return;
        }

        $invoice->assignLocalInvoiceNo();
    }

    /** Stock held in other cashiers' in-progress carts (base units). */
    private function reservedByOthers(int $productId, ?int $userId): float
    {
        $buckets = PosCartReservation::reservedBucketsForProduct($productId, $userId);

        return (float) array_sum($buckets['per_batch'] ?? []) + (float) array_sum($buckets['per_price'] ?? []);
    }

    /** @return array{0: float, 1: float} [paid cost per base unit, purchase tax per base unit] */
    private function lotCost(int $purchaseId): array
    {
        $cache = &$this->lotCostCache;
        if (! isset($cache[$purchaseId])) {
            $purchase = Purchase::withoutGlobalScopes()->find($purchaseId);
            if (! $purchase || (float) $purchase->base_quantity <= 0) {
                $cache[$purchaseId] = [0.0, 0.0];
            } else {
                $paidTotal = (float) $purchase->paid_extra_total_cost_price > 0
                    ? (float) $purchase->paid_extra_total_cost_price
                    : (float) $purchase->total_cost_price;
                $cache[$purchaseId] = [
                    round($paidTotal / (float) $purchase->base_quantity, 6),
                    (float) $purchase->base_unit_purchase_tax_price,
                ];
            }
        }

        return $cache[$purchaseId];
    }

    private function loadRoleRules(?int $userId, int $businessId): void
    {
        $user = $userId ? \App\Models\User::find($userId) : null;
        $permissions = app(\App\Services\Access\PermissionService::class);

        // No user (e.g. system jobs) = no role restriction; the business and product rules still apply.
        $this->roleCanDiscount = ! $user || $permissions->allows($user, $businessId, 'pos.discount');
        $this->roleCanChangePrice = ! $user || $permissions->allows($user, $businessId, 'pos.price_change');
        $max = $user ? $permissions->limit($user, $businessId, 'max_discount_percent') : null;
        $this->roleMaxDiscountPercent = is_numeric($max) ? (float) $max : null;
    }

    private function minNullable(?float $a, ?float $b): ?float
    {
        if ($a === null) return $b;
        if ($b === null) return $a;

        return min($a, $b);
    }
}
