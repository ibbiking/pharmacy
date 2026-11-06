<?php

namespace App\Services\Stock;

use App\Models\BaseStockSalePrice;
use App\Models\InvoiceItemLot;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseTax;
use App\Models\SaleTax;
use App\Models\StockPrices;
use App\Models\Supplier;
use App\Models\Tax;
use App\Services\Sales\PackagingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchases (stock in), Roadmap 5.5a. The server is the authority for every amount; the browser only sends what was
 * bought. One purchase = one batch (lot). Written exactly as the sale engine reads it:
 *   purchases                 paid_extra_total_cost_price, base_quantity, base_unit_purchase_tax_price, extra_paid_percent
 *   purchase_taxes / sale_taxes  one row per tax, rate from the business's Tax (sale taxes drive the sale-time tax)
 *   base_stock_sale_price     the lot: base_stock, remaining_base_stock, base sale price + sale tax per base unit, expiry
 *   stock_prices              legacy per-purchase price row (still read by old product screens)
 *   product_stocks            derived counter (sum of remaining), kept in step
 *
 * Amounts: total = unit × qty; GST/extra paid = paid unit cost − unit cost (disclosure on receipts);
 * purchase tax = unit cost × rate; sale tax = unit sale price × rate; base-unit prices = totals ÷ base quantity.
 * Edit: once anything was sold from the batch the product and unit are locked and the quantity can't go below what was
 * sold. Archive: only when nothing was sold from the batch.
 */
class PurchaseService
{
    public function __construct(private readonly PackagingService $packaging)
    {
    }

    /**
     * @param  array{supplier?: mixed, invoice_no?: ?string, lines: array<int, array<string, mixed>>}  $input
     * @return Collection<int, Purchase>
     */
    public function create(array $input): Collection
    {
        $head = $this->validateHead($input);
        $lines = $input['lines'] ?? null;
        if (! is_array($lines) || $lines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one item.']);
        }
        if (count($lines) > 100) {
            throw ValidationException::withMessages(['lines' => 'Use at most 100 items per purchase.']);
        }

        $checked = [];
        $errors = [];
        foreach (array_values($lines) as $i => $line) {
            try {
                $checked[] = $this->checkLine(is_array($line) ? $line : [], null);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors["lines.{$i}.{$key}"] = $messages;
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($head, $checked) {
            $supplierId = $this->resolveSupplier($head['supplier']);

            return collect($checked)->map(fn (array $l) => $this->write(new Purchase, $l, $supplierId, $head['invoice_no'], 0.0));
        });
    }

    /** Update one purchase (its supplier bill header and its line). */
    public function update(Purchase $purchase, array $input): Purchase
    {
        $head = $this->validateHead($input);
        try {
            $line = $this->checkLine(is_array($input['line'] ?? null) ? $input['line'] : [], $purchase);
        } catch (ValidationException $e) {
            // Same error keys the form uses: "line.<field>" (create uses "lines.<i>.<field>").
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["line.{$k}" => $m])->all());
        }
        $sold = $this->soldBase($purchase);

        if ($sold > 0) {
            if ((int) $line['product']->id !== (int) $purchase->product_id || (int) $line['category_id'] !== (int) $purchase->category_id) {
                throw ValidationException::withMessages(['line.category_id' => 'Items from this batch were already sold, so the product and unit can’t change.']);
            }
            if ($line['base_qty'] + 1e-6 < $sold) {
                $soldIn = round($this->packaging->fromBase((int) $purchase->product_id, (int) $purchase->category_id, $sold), 2);
                throw ValidationException::withMessages(['line.quantity' => "{$soldIn} were already sold from this batch; the quantity can’t be lower."]);
            }
        }

        return DB::transaction(function () use ($purchase, $head, $line, $sold) {
            $oldProductId = (int) $purchase->product_id;
            $oldRemaining = (float) (BaseStockSalePrice::query()->where('purchase_id', $purchase->id)->value('remaining_base_stock') ?? 0);
            $supplierId = $this->resolveSupplier($head['supplier']);
            $this->write($purchase, $line, $supplierId, $head['invoice_no'], $sold);

            // Stock counter: take the old remaining off the old product, add the new remaining to the product.
            $this->bumpCounter($oldProductId, -$oldRemaining, null);
            $this->bumpCounter((int) $line['product']->id, $line['base_qty'] - $sold, $line['base_category_id']);

            return $purchase->fresh();
        });
    }

    /** Archive a purchase that nothing was sold from; its remaining stock leaves the shelf. */
    public function archive(Purchase $purchase): void
    {
        if ($reason = $this->archiveBlocker($purchase)) {
            throw ValidationException::withMessages(['purchase' => $reason]);
        }

        DB::transaction(function () use ($purchase) {
            $remaining = (float) BaseStockSalePrice::query()->where('purchase_id', $purchase->id)->sum('remaining_base_stock');
            BaseStockSalePrice::query()->where('purchase_id', $purchase->id)->get()->each->delete();
            StockPrices::query()->where('purchase_id', $purchase->id)->get()->each->delete();
            PurchaseTax::query()->where('purchase_id', $purchase->id)->get()->each->delete();
            SaleTax::query()->where('purchase_id', $purchase->id)->get()->each->delete();
            $this->bumpCounter((int) $purchase->product_id, -$remaining, null);
            $purchase->delete();
        });
    }

    public function archiveBlocker(Purchase $purchase): ?string
    {
        return $this->soldBase($purchase) > 0
            ? 'Items from this batch were already sold or returned, so it can’t be archived (invoices refer to it). Correct the quantity instead.'
            : null;
    }

    /** Base units that left this batch through sales (net of returns), or that invoices refer to. */
    public function soldBase(Purchase $purchase): float
    {
        $lot = BaseStockSalePrice::query()->where('purchase_id', $purchase->id)->first();
        $consumed = $lot ? max(0.0, (float) $lot->base_stock - (float) $lot->remaining_base_stock) : 0.0;
        if ($consumed <= 0 && InvoiceItemLot::query()->where('purchase_id', $purchase->id)->exists()) {
            return 0.000001; // fully returned, but invoices still point at this batch: treat as used
        }

        return round($consumed, 6);
    }

    /** The amounts the server will store for one line (used by the live preview too). */
    public function quote(array $line): array
    {
        $l = $this->checkLine($line, null, quoteOnly: true);

        return [
            'base_qty' => $l['base_qty'], 'total_cost' => $l['total_cost'], 'total_paid' => $l['total_paid'], 'extra_per_unit' => $l['extra_per_unit'],
            'extra_percent' => $l['extra_percent'], 'purchase_tax_total' => $l['purchase_tax_total'], 'sale_tax_total' => $l['sale_tax_total'],
            'total_sale' => $l['total_sale'], 'margin_percent' => $l['paid'] > 0 ? round(($l['sale'] - $l['paid']) / $l['paid'] * 100, 2) : null,
        ];
    }

    // ------------------------------------------------------------------------------------------------ internals

    private function validateHead(array $input): array
    {
        $data = validator($input, [
            'supplier' => ['required', 'array'],
            'invoice_no' => ['nullable', 'string', 'max:100'],
        ], ['supplier.required' => 'Pick the supplier, or type a new name.'])->validate();

        return ['supplier' => $data['supplier'], 'invoice_no' => trim((string) ($data['invoice_no'] ?? '')) ?: null];
    }

    private function checkLine(array $line, ?Purchase $existing, bool $quoteOnly = false): array
    {
        $data = validator($line, [
            'product_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'unit_cost_price' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'paid_unit_cost_price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'unit_sale_price' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => [$quoteOnly ? 'nullable' : 'required', 'date'],
            'purchase_tax_ids' => ['nullable', 'array', 'max:10'],
            'purchase_tax_ids.*' => ['integer', 'distinct'],
            'sale_tax_ids' => ['nullable', 'array', 'max:10'],
            'sale_tax_ids.*' => ['integer', 'distinct'],
        ], [
            'expiry_date.required' => 'Enter the expiry date printed on the pack.',
        ], [
            'product_id' => 'product', 'category_id' => 'unit', 'unit_cost_price' => 'unit cost', 'paid_unit_cost_price' => 'paid unit cost',
            'unit_sale_price' => 'sale price',
        ])->validate();

        if (! empty($data['manufacturing_date']) && ! empty($data['expiry_date']) && strtotime($data['expiry_date']) <= strtotime($data['manufacturing_date'])) {
            throw ValidationException::withMessages(['expiry_date' => 'The expiry date must be after the manufacturing date.']);
        }

        // Business-scoped lookups: another business's product, unit or tax simply isn't found.
        $product = Product::query()->whereKey($data['product_id'])->first();
        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'Pick a product from your list.']);
        }
        if ($product->is_draft) {
            throw ValidationException::withMessages(['product_id' => 'Finish this product’s packaging setup before buying stock.']);
        }
        $categoryId = (int) $data['category_id'];
        $units = array_map('intval', array_keys($this->packaging->units($product->id))); // [unit id => base units in one]
        if (! in_array($categoryId, $units, true)) {
            throw ValidationException::withMessages(['category_id' => 'Pick one of this product’s packaging units.']);
        }

        $qty = round((float) $data['quantity'], 4);
        [$baseCategoryId, $baseQty] = $this->packaging->toBase($product->id, $categoryId, $qty);
        $baseQty = round((float) $baseQty, 4);
        if ($baseQty <= 0) {
            throw ValidationException::withMessages(['quantity' => 'This quantity is less than one base unit.']);
        }

        $cost = round((float) $data['unit_cost_price'], 4);
        $paid = isset($data['paid_unit_cost_price']) && $data['paid_unit_cost_price'] !== '' ? round((float) $data['paid_unit_cost_price'], 4) : $cost;
        if ($paid + 1e-9 < $cost) {
            throw ValidationException::withMessages(['paid_unit_cost_price' => 'What you paid per unit can’t be less than the unit cost (it adds the sales tax you paid).']);
        }
        $sale = round((float) $data['unit_sale_price'], 4);
        if ($sale <= $paid) {
            throw ValidationException::withMessages(['unit_sale_price' => 'The sale price must be higher than what you paid per unit.']);
        }

        $taxes = function (string $key) use ($data) {
            $ids = array_values(array_unique(array_map('intval', $data[$key] ?? [])));
            $found = Tax::query()->whereIn('id', $ids)->get(['id', 'name', 'rate'])->keyBy('id');
            if ($found->count() !== count($ids)) {
                throw ValidationException::withMessages([$key => 'Pick taxes from your tax list.']);
            }

            return $found->values();
        };
        $purchaseTaxes = $taxes('purchase_tax_ids');
        $saleTaxes = $taxes('sale_tax_ids');
        $taxRows = fn ($list, float $unitPrice) => $list->map(fn ($t) => [
            'tax_id' => (int) $t->id, 'rate' => (float) $t->rate,
            'unit' => round($unitPrice * (float) $t->rate / 100, 6), 'total' => round($unitPrice * (float) $t->rate / 100 * $qty, 6),
        ])->all();
        $pRows = $taxRows($purchaseTaxes, $cost);
        $sRows = $taxRows($saleTaxes, $sale);

        $extra = round($paid - $cost, 6);

        return [
            'product' => $product, 'category_id' => $categoryId, 'base_category_id' => (int) $baseCategoryId, 'qty' => $qty, 'base_qty' => $baseQty,
            'cost' => $cost, 'paid' => $paid, 'sale' => $sale,
            'extra_per_unit' => $extra, 'extra_percent' => $cost > 0 ? round($extra / $cost * 100, 4) : 0.0,
            'total_cost' => round($cost * $qty, 6), 'total_paid' => round($paid * $qty, 6), 'total_sale' => round($sale * $qty, 6),
            'purchase_taxes' => $pRows, 'sale_taxes' => $sRows,
            'purchase_tax_unit' => round(array_sum(array_column($pRows, 'unit')), 6), 'purchase_tax_total' => round(array_sum(array_column($pRows, 'total')), 6),
            'sale_tax_unit' => round(array_sum(array_column($sRows, 'unit')), 6), 'sale_tax_total' => round(array_sum(array_column($sRows, 'total')), 6),
            'batch_no' => trim((string) ($data['batch_no'] ?? '')) ?: null,
            'mfg' => $data['manufacturing_date'] ?? null, 'expiry' => $data['expiry_date'] ?? null,
        ];
    }

    private function write(Purchase $purchase, array $l, int $supplierId, ?string $invoiceNo, float $sold): Purchase
    {
        $b = $l['base_qty'];
        $perBase = fn (float $total) => round($total / $b, 6);
        $productId = (int) $l['product']->id;
        $isNew = ! $purchase->exists;

        $purchase->fill([
            'product_id' => $productId, 'category_id' => $l['category_id'], 'supplier_id' => $supplierId,
            'invoice_no' => $invoiceNo, 'batch_no' => $l['batch_no'],
            'quantity' => $l['qty'], 'base_category_id' => $l['base_category_id'], 'base_quantity' => $b,
            'manufacturing_date' => $l['mfg'], 'expiry_date' => $l['expiry'],
            'unit_cost_price' => $l['cost'], 'total_cost_price' => $l['total_cost'],
            'unit_cost_tax_amount' => $l['purchase_tax_unit'], 'total_cost_tax_amount' => $l['purchase_tax_total'],
            'unit_sale_price' => $l['sale'], 'total_sale_price' => $l['total_sale'],
            'unit_sale_tax_amount' => $l['sale_tax_unit'], 'total_sale_tax_amount' => $l['sale_tax_total'],
            'base_unit_purchase_price' => $perBase($l['total_cost']),
            'base_unit_purchase_tax_price' => $perBase($l['purchase_tax_total']),
            'base_unit_total_purchase_tax_price' => $perBase($l['total_cost'] + $l['purchase_tax_total']),
            'base_unit_sale_price' => $perBase($l['total_sale']),
            'base_unit_sale_tax_price' => $perBase($l['sale_tax_total']),
            'base_unit_total_sale_tax_price' => $perBase($l['total_sale'] + $l['sale_tax_total']),
            'paid_unit_cost_price' => $l['paid'], 'extra_paid_per_unit' => $l['extra_per_unit'],
            'extra_paid_percent' => $l['extra_percent'], 'paid_extra_total_cost_price' => $l['total_paid'],
        ])->save();

        // Old tax rows are archived (soft delete), not erased.
        PurchaseTax::query()->where('purchase_id', $purchase->id)->get()->each->delete();
        SaleTax::query()->where('purchase_id', $purchase->id)->get()->each->delete();
        foreach ($l['purchase_taxes'] as $t) {
            PurchaseTax::create(['purchase_id' => $purchase->id, 'product_id' => $productId, 'tax_id' => $t['tax_id'], 'tax_rate' => $t['rate'], 'tax_unit_amount' => $t['unit'], 'tax_amount' => $t['total']]);
        }
        foreach ($l['sale_taxes'] as $t) {
            SaleTax::create(['purchase_id' => $purchase->id, 'product_id' => $productId, 'tax_id' => $t['tax_id'], 'tax_rate' => $t['rate'], 'tax_unit_amount' => $t['unit'], 'tax_amount' => $t['total']]);
        }

        $dates = ['manufacturing_date' => $l['mfg'], 'expiry_date' => $l['expiry']];
        StockPrices::query()->updateOrCreate(['purchase_id' => $purchase->id], [
            'product_id' => $productId, 'category_id' => $l['category_id'], 'base_category_id' => $l['base_category_id'],
            'base_stock' => $b, 'category_stock' => $l['qty'],
            'category_unit_purchase_price' => $l['cost'], 'category_unit_purchase_tax_price' => $l['purchase_tax_unit'],
            'category_unit_total_purchase_tax_price' => round($l['cost'] + $l['purchase_tax_unit'], 6),
            'category_unit_sale_price' => $l['sale'], 'category_unit_sale_tax_price' => $l['sale_tax_unit'],
            'category_unit_total_sale_tax_price' => round($l['sale'] + $l['sale_tax_unit'], 6),
            'base_category_unit_purchase_price' => $perBase($l['total_cost']), 'base_category_unit_purchase_tax_price' => $perBase($l['purchase_tax_total']),
            'base_category_unit_total_purchase_tax_price' => $perBase($l['total_cost'] + $l['purchase_tax_total']),
            'base_category_unit_sale_price' => $perBase($l['total_sale']), 'base_category_unit_sale_tax_price' => $perBase($l['sale_tax_total']),
            'base_category_unit_total_sale_tax_price' => $perBase($l['total_sale'] + $l['sale_tax_total']),
        ] + $dates);

        BaseStockSalePrice::query()->updateOrCreate(['purchase_id' => $purchase->id], [
            'product_id' => $productId, 'category_id' => $l['category_id'], 'base_category_id' => $l['base_category_id'],
            'base_stock' => $b, 'remaining_base_stock' => round($b - $sold, 6),
            'base_category_unit_sale_price' => $perBase($l['total_sale']), 'base_category_unit_sale_tax_price' => $perBase($l['sale_tax_total']),
        ] + $dates);

        if ($isNew) {
            $this->bumpCounter($productId, $b, $l['base_category_id']);
        }
        $this->packaging->forget($productId);

        return $purchase;
    }

    private function bumpCounter(int $productId, float $delta, ?int $baseCategoryId): void
    {
        $stock = ProductStock::query()->where('product_id', $productId)->first();
        if (! $stock) {
            if ($delta > 0) {
                ProductStock::create(['product_id' => $productId, 'base_category_id' => $baseCategoryId, 'current_stock' => $delta]);
            }

            return;
        }
        $stock->current_stock = max(0, round((float) $stock->current_stock + $delta, 6));
        if ($baseCategoryId) {
            $stock->base_category_id = $baseCategoryId;
        }
        $stock->save();
    }

    private function resolveSupplier(array $ref): int
    {
        if (! empty($ref['id'])) {
            $supplier = Supplier::query()->find((int) $ref['id']);
            if (! $supplier) {
                throw ValidationException::withMessages(['supplier' => 'This supplier no longer exists. Pick another one.']);
            }

            return (int) $supplier->id;
        }
        $name = trim((string) ($ref['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['supplier' => 'Pick the supplier, or type a new name (up to 100 characters).']);
        }

        return (int) (Supplier::query()->where('name', $name)->value('id') ?? Supplier::create(['name' => $name])->id);
    }
}
