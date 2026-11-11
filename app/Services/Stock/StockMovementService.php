<?php

namespace App\Services\Stock;

use App\Models\BaseStockSalePrice;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockAdjustmentLine;
use App\Models\Inventory\StockAdjustmentLot;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\StockTransferLine;
use App\Models\Inventory\StockTransferLot;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Services\Numbering\NumberSeriesService;
use App\Services\Sales\PackagingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock moves that are not purchases or sales (Inventory Pro, Roadmap step 7), on the same lots the POS sells from.
 * - Transfer: takes FEFO from the source warehouse (expired batches too: they can be moved to a quarantine store);
 *   each moved part becomes (or tops up) a lot of the same purchase in the target warehouse, so cost and expiry travel
 *   with it. The product's stock counter does not change.
 * - Adjustment decrease: takes FEFO from the warehouse; the cost value leaves the books (Accounting: Dr Inventory
 *   adjustments, Cr Inventory). Increase: adds to an existing batch (the latest of that product in the warehouse, or
 *   the one picked), valued at that batch's cost.
 */
class StockMovementService
{
    public function __construct(
        private readonly PackagingService $packaging,
        private readonly WarehouseService $warehouses,
        private readonly NumberSeriesService $numbers,
    ) {
    }

    public function transfer(array $input, ?int $userId): StockTransfer
    {
        $businessId = $this->businessId();
        $data = validator($input, [
            'from_warehouse_id' => ['required', 'integer'],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
        ], ['to_warehouse_id.different' => 'Pick a different warehouse to move the stock to.', 'lines.required' => 'Add at least one item.'],
            ['from_warehouse_id' => 'from warehouse', 'to_warehouse_id' => 'to warehouse'])->validate();
        $from = $this->warehouse($data['from_warehouse_id'], 'from_warehouse_id');
        $to = $this->warehouse($data['to_warehouse_id'], 'to_warehouse_id');
        $lines = $this->lines($data['lines']);

        return DB::transaction(function () use ($data, $from, $to, $lines, $userId, $businessId) {
            $transfer = StockTransfer::create([
                'number' => $this->numbers->next($businessId, 'transfer'), 'from_warehouse_id' => $from, 'to_warehouse_id' => $to,
                'date' => $data['date'], 'note' => $data['note'] ?? null, 'created_by' => $userId,
            ]);
            foreach ($lines as $i => $l) {
                $line = StockTransferLine::create(['stock_transfer_id' => $transfer->id, 'product_id' => $l['product']->id, 'category_id' => $l['category_id'], 'qty' => $l['qty'], 'base_qty' => $l['base_qty']]);
                foreach ($this->take($l, $from, $i) as [$lot, $qty]) {
                    $target = BaseStockSalePrice::query()->where('purchase_id', $lot->purchase_id)->where('warehouse_id', $to)->lockForUpdate()->first();
                    if ($target) {
                        $target->base_stock = round((float) $target->base_stock + $qty, 6);
                        $target->remaining_base_stock = round((float) $target->remaining_base_stock + $qty, 6);
                        $target->save();
                    } else {
                        $target = $lot->replicate();
                        $target->warehouse_id = $to;
                        $target->base_stock = $qty;
                        $target->remaining_base_stock = $qty;
                        $target->save();
                    }
                    $lot->base_stock = round((float) $lot->base_stock - $qty, 6);
                    $lot->save();
                    StockTransferLot::create(['stock_transfer_line_id' => $line->id, 'from_lot_id' => $lot->id, 'to_lot_id' => $target->id, 'base_qty' => $qty]);
                }
            }

            return $transfer;
        });
    }

    public function adjust(array $input, ?int $userId): StockAdjustment
    {
        $businessId = $this->businessId();
        $data = validator($input, [
            'warehouse_id' => ['nullable', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'direction' => ['required', 'in:increase,decrease'],
            'reason' => ['required', 'in:'.implode(',', array_keys(StockAdjustment::REASONS))],
            'note' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
        ], ['lines.required' => 'Add at least one item.'])->validate();
        $warehouseId = $this->warehouses->enabled($businessId) ? $this->warehouse($data['warehouse_id'] ?? $this->warehouses->defaultId($businessId), 'warehouse_id') : null;
        $lines = $this->lines($data['lines']);

        $adjustment = DB::transaction(function () use ($data, $warehouseId, $lines, $userId, $businessId) {
            $adjustment = StockAdjustment::create([
                'number' => $this->numbers->next($businessId, 'adjustment'), 'warehouse_id' => $warehouseId, 'date' => $data['date'],
                'direction' => $data['direction'], 'reason' => $data['reason'], 'note' => $data['note'] ?? null, 'created_by' => $userId,
            ]);
            $total = 0.0;
            foreach ($lines as $i => $l) {
                $line = StockAdjustmentLine::create(['stock_adjustment_id' => $adjustment->id, 'product_id' => $l['product']->id, 'category_id' => $l['category_id'], 'qty' => $l['qty'], 'base_qty' => $l['base_qty']]);
                $value = 0.0;
                $moves = $data['direction'] === 'decrease' ? $this->take($l, $warehouseId, $i) : [[$this->batchToTopUp($l, $warehouseId, $i), $l['base_qty']]];
                foreach ($moves as [$lot, $qty]) {
                    $cost = $this->baseCost((int) $lot->purchase_id);
                    if ($data['direction'] === 'increase') {
                        $lot->base_stock = round((float) $lot->base_stock + $qty, 6);
                        $lot->remaining_base_stock = round((float) $lot->remaining_base_stock + $qty, 6);
                        $lot->save();
                    }
                    StockAdjustmentLot::create(['stock_adjustment_line_id' => $line->id, 'lot_id' => $lot->id, 'purchase_id' => $lot->purchase_id, 'base_qty' => $qty, 'base_unit_cost' => $cost]);
                    $value += $qty * $cost;
                }
                $this->bumpCounter($l['product']->id, $data['direction'] === 'decrease' ? -$l['base_qty'] : $l['base_qty']);
                $line->update(['value' => round($value, 2)]);
                $total += $value;
            }
            $adjustment->update(['value' => round($total, 2)]);
            event(new \App\Events\StockAdjusted($adjustment));

            return $adjustment;
        });

        return $adjustment->fresh('lines');
    }

    /** Paid cost of one base unit of a batch (same as the sale engine's COGS). */
    public function baseCost(int $purchaseId): float
    {
        $p = Purchase::withTrashed()->find($purchaseId);
        if (! $p || (float) $p->base_quantity <= 0) {
            return 0.0;
        }
        $paid = (float) $p->paid_extra_total_cost_price > 0 ? (float) $p->paid_extra_total_cost_price : (float) $p->total_cost_price;

        return round($paid / (float) $p->base_quantity, 6);
    }

    // ------------------------------------------------------------------------------------------------ internals

    private function businessId(): ?int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id')) ?: null;
    }

    private function warehouse($id, string $field): int
    {
        $found = Warehouse::query()->whereKey((int) $id)->value('id');
        if (! $found) {
            throw ValidationException::withMessages([$field => 'Pick one of your warehouses.']);
        }

        return (int) $found;
    }

    /** @return array<int, array{product: Product, category_id: int, qty: float, base_qty: float}> */
    private function lines(array $lines): array
    {
        $out = [];
        $errors = [];
        foreach (array_values($lines) as $i => $line) {
            $v = validator(is_array($line) ? $line : [], [
                'product_id' => ['required', 'integer'], 'category_id' => ['required', 'integer'], 'quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
                'lot_id' => ['nullable', 'integer'],
            ], [], ['product_id' => 'item', 'category_id' => 'unit']);
            if ($v->fails()) {
                foreach ($v->errors()->toArray() as $k => $m) {
                    $errors["lines.{$i}.{$k}"] = $m;
                }
                continue;
            }
            $d = $v->validated();
            $product = Product::query()->whereKey($d['product_id'])->first();
            if (! $product || $product->is_draft) {
                $errors["lines.{$i}.product_id"] = ['Pick a set-up item from your list.'];
                continue;
            }
            if (! array_key_exists((int) $d['category_id'], $this->packaging->units($product->id))) {
                $errors["lines.{$i}.category_id"] = ['Pick one of this item’s units.'];
                continue;
            }
            [, $baseQty] = $this->packaging->toBase($product->id, (int) $d['category_id'], (float) $d['quantity']);
            $out[] = ['product' => $product, 'category_id' => (int) $d['category_id'], 'qty' => round((float) $d['quantity'], 4), 'base_qty' => round((float) $baseQty, 4), 'lot_id' => $d['lot_id'] ?? null];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Lots to take $l from (FEFO, expired included), locked and already reduced. @return array<int, array{0: BaseStockSalePrice, 1: float}> */
    private function take(array $l, ?int $warehouseId, int $i): array
    {
        $lots = BaseStockSalePrice::query()->where('product_id', $l['product']->id)->where('remaining_base_stock', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
        $needed = $l['base_qty'];
        $have = (float) $lots->sum('remaining_base_stock');
        if (round($have, 4) < round($needed, 4)) {
            $haveIn = round($this->packaging->fromBase($l['product']->id, $l['category_id'], $have), 2);
            throw ValidationException::withMessages(["lines.{$i}.quantity" => "Only {$haveIn} of {$l['product']->product_name} in stock there."]);
        }
        $out = [];
        foreach ($lots as $lot) {
            if ($needed <= 0.00001) {
                break;
            }
            $qty = min($needed, (float) $lot->remaining_base_stock);
            $lot->remaining_base_stock = round((float) $lot->remaining_base_stock - $qty, 6);
            $lot->save();
            $needed -= $qty;
            $out[] = [$lot, round($qty, 6)];
        }

        return $out;
    }

    private function batchToTopUp(array $l, ?int $warehouseId, int $i): BaseStockSalePrice
    {
        $lot = BaseStockSalePrice::query()->where('product_id', $l['product']->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($l['lot_id'], fn ($q) => $q->whereKey($l['lot_id']))
            ->latest('id')->lockForUpdate()->first();
        if (! $lot) {
            throw ValidationException::withMessages(["lines.{$i}.product_id" => 'This item has no batch here yet: record a purchase to add new stock.']);
        }

        return $lot;
    }

    private function bumpCounter(int $productId, float $delta): void
    {
        $stock = ProductStock::query()->where('product_id', $productId)->lockForUpdate()->first();
        if ($stock) {
            $stock->current_stock = max(0, round((float) $stock->current_stock + $delta, 6));
            $stock->save();
        } elseif ($delta > 0) {
            ProductStock::create(['product_id' => $productId, 'base_category_id' => $this->packaging->baseCategoryId($productId), 'current_stock' => $delta]);
        }
    }
}
