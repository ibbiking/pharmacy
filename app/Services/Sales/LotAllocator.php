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
    /** Sellable lots for a product in the given order. Pass $lock = true inside a transaction to lock them. */
    public function sellableLots(int $productId, string $lotMode, bool $lock = false): Collection
    {
        $query = BaseStockSalePrice::query()
            ->where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where(fn (Builder $q) => $q->whereNull('expiry_date')->orWhereDate('expiry_date', '>', today()));

        $this->applyOrder($query, $lotMode);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function applyOrder(Builder $query, string $lotMode): Builder
    {
        if ($lotMode === SaleSettings::LOT_FEFO) {
            // NULL expiry sorts last under FEFO.
            $query->orderByRaw('expiry_date IS NULL')->orderBy('expiry_date')->orderBy('id');
        } else {
            $query->orderBy('id');
        }

        return $query;
    }

    /** Re-order an already-loaded set of lots (e.g. one locked set shared by lines with different modes). */
    public function sort(Collection $lots, string $lotMode): Collection
    {
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
