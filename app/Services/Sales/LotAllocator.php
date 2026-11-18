<?php

namespace App\Services\Sales;

use App\Models\BaseStockSalePrice;
use App\Services\Sales\Exceptions\InsufficientStockException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides which stock lots (base_stock_sale_price rows, one per purchase) a sale takes from.
 *
 * Single source of truth for lot ORDER, used by: POS availability, stock-wise pricing, the tax
 * breakdown, checkout deduction. Two orders (decision D1):
 *   - FEFO: earliest expiry first (ties: oldest purchase first)
 *   - FIFO: oldest purchase first
 *
 * Sellable = remaining stock > 0 and not expired. A lot is sellable up to the day BEFORE its
 * expiry date (unchanged from the previous behaviour: `expiry_date >= now()` was false all day
 * on the expiry date itself).
 */
class LotAllocator
{
    /**
     * Sellable lots for a product in the given order. Pass $lock = true inside a transaction to lock them.
     * $warehouseId: null = the POS warehouse of the business (Inventory Pro; every warehouse when unset or when the
     * module is off), false = every warehouse, an id = only that warehouse.
     */
    public function sellableLots(int $productId, string $lotMode, bool $lock = false, int|false|null $warehouseId = null): Collection
    {
        $query = $this->sellableQuery($productId, $warehouseId);

        $this->applyOrder($query, $lotMode);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Base units the POS can sell right now: the same lots checkout allocates from (POS warehouse, not expired;
     * lots without an expiry date never expire). Search and stock checks must use this so they agree with checkout.
     */
    public function sellableQuantity(int $productId, int|false|null $warehouseId = null): float
    {
        return (float) $this->sellableQuery($productId, $warehouseId)->sum('base_stock_sale_price.remaining_base_stock');
    }

    /** Lots that can be sold: in stock, not expired (NULL expiry = never expires), optionally one warehouse. */
    public function sellableQuery(int $productId, int|false|null $warehouseId = null): Builder
    {
        if ($warehouseId === null) {
            $businessId = (int) (session('impersonate_business_id') ?? session('business_id')) ?: null;
            $warehouseId = app(\App\Services\Stock\WarehouseService::class)->posWarehouseId($businessId) ?? false;
        }

        // Columns are table-qualified so callers can join purchases (batch no) without ambiguity.
        $t = (new BaseStockSalePrice)->getTable();

        return BaseStockSalePrice::query()
            ->where("{$t}.product_id", $productId)
            ->when($warehouseId !== false, fn (Builder $q) => $q->where("{$t}.warehouse_id", $warehouseId))
            ->where("{$t}.remaining_base_stock", '>', 0)
            ->where(fn (Builder $q) => $q->whereNull("{$t}.expiry_date")->orWhereDate("{$t}.expiry_date", '>', today()));
    }

    public function applyOrder(Builder $query, string $lotMode): Builder
    {
        $t = $query->getModel()->getTable();
        if ($lotMode === SaleSettings::LOT_FEFO) {
            // NULL expiry sorts last under FEFO.
            $query->orderByRaw("{$t}.expiry_date IS NULL")->orderBy("{$t}.expiry_date")->orderBy("{$t}.id");
        } elseif ($lotMode === SaleSettings::LOT_ANY) {
            // "Any batch": no expiry/purchase order; take the fullest batch so the fewest batches get split.
            $query->orderByDesc("{$t}.remaining_base_stock")->orderBy("{$t}.id");
        } else {
            $query->orderBy("{$t}.id");
        }

        return $query;
    }

    /** Re-order an already-loaded set of lots (e.g. one locked set shared by lines with different modes). */
    public function sort(Collection $lots, string $lotMode): Collection
    {
        if ($lotMode === SaleSettings::LOT_ANY) {
            return $lots->sort(fn ($a, $b) => [(float) $b->remaining_base_stock, $a->id] <=> [(float) $a->remaining_base_stock, $b->id])->values();
        }
        if ($lotMode !== SaleSettings::LOT_FEFO) {
            return $lots->sortBy('id')->values();
        }

        return $lots->sort(function ($a, $b) {
            $ea = $a->expiry_date ? (string) $a->expiry_date : null;
            $eb = $b->expiry_date ? (string) $b->expiry_date : null;
            if ($ea !== $eb) {
                if ($ea === null) return 1;
                if ($eb === null) return -1;
                return strcmp($ea, $eb);
            }

            return $a->id <=> $b->id;
        })->values();
    }

    /**
     * Allocate $baseQty from $lots without touching the database.
     *
     * @param  Collection  $lots       lots in the desired order
     * @param  array       $remaining  lotId => remaining base qty, shared across lines and updated in place
     * @param  int|null    $preferredLotId  take from this lot first (stock-wise lines are tied to a lot)
     * @return array<int, array{lot: BaseStockSalePrice, qty: float}>
     *
     * @throws InsufficientStockException
     */
    public function allocate(Collection $lots, array &$remaining, float $baseQty, ?int $preferredLotId = null): array
    {
        $allocations = [];
        $needed = $baseQty;

        $ordered = $lots;
        if ($preferredLotId) {
            $preferred = $lots->firstWhere('id', $preferredLotId);
            if ($preferred) {
                $ordered = collect([$preferred])->merge($lots->reject(fn ($l) => $l->id === $preferredLotId))->values();
            }
        }

        foreach ($ordered as $lot) {
            if (round($needed, 4) <= 0) {
                break;
            }

            $available = $remaining[$lot->id] ?? (float) $lot->remaining_base_stock;
            if ($available <= 0) {
                continue;
            }

            $take = min($needed, $available);
            $remaining[$lot->id] = $available - $take;
            $needed -= $take;
            $allocations[] = ['lot' => $lot, 'qty' => $take];
        }

        if (round($needed, 4) > 0) {
            throw new InsufficientStockException((int) ($lots->first()->product_id ?? 0), $baseQty, $baseQty - $needed);
        }

        return $allocations;
    }
}
