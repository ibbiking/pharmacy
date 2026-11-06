<?php

namespace App\Services\Sales;

use App\Models\ProductParameter;
use InvalidArgumentException;

/**
 * Packaging hierarchy maths for a product, e.g. Box → Strip (1 per box) → Tablet (14 per strip).
 *
 * ProductParameter rows describe the chain: a root "self" row (parent == child) plus one row per
 * link (parent → child, quantity = how many children fit in one parent). The last child in the
 * chain is the BASE unit; all stock is counted in base units.
 *
 * This is the single implementation every caller should use (pricing, stock, POS, returns,
 * reports). Results are memoised per product for the lifetime of the service instance.
 */
class PackagingService
{
    /** @var array<int, array{base: ?int, units: array<int, float>}> productId => base category + base units per category */
    private array $cache = [];

    /** Base category (smallest unit) of the product, or null if it has no packaging rows. */
    public function baseCategoryId(int $productId): ?int
    {
        return $this->map($productId)['base'];
    }

    /** How many base units one unit of $categoryId contains (1 for the base unit itself). */
    public function baseUnitsPer(int $productId, ?int $categoryId): float
    {
        $map = $this->map($productId);

        if ($categoryId === null || $categoryId === $map['base']) {
            return 1.0;
        }

        if (! isset($map['units'][$categoryId])) {
            throw new InvalidArgumentException("Category {$categoryId} is not part of product {$productId}'s packaging.");
        }

        return $map['units'][$categoryId];
    }

    /** @return array{0: ?int, 1: float} [baseCategoryId, baseQty] */
    public function toBase(int $productId, ?int $categoryId, float $qty): array
    {
        return [$this->baseCategoryId($productId), $qty * $this->baseUnitsPer($productId, $categoryId)];
    }

    public function fromBase(int $productId, ?int $categoryId, float $baseQty): float
    {
        return $baseQty / $this->baseUnitsPer($productId, $categoryId);
    }

    /** All packaging units of the product with their base-unit multipliers, largest first. */
    public function units(int $productId): array
    {
        $units = $this->map($productId)['units'];
        arsort($units);

        return $units;
    }

    public function forget(?int $productId = null): void
    {
        if ($productId === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$productId]);
        }
    }

    private function map(int $productId): array
    {
        if (isset($this->cache[$productId])) {
            return $this->cache[$productId];
        }

        // Any business (callers pass ids they already resolved), but never archived rows.
        $rows = ProductParameter::withoutGlobalScope(\App\Models\Scopes\BusinessScope::class)->where('product_id', $productId)->get();

        $childOf = [];      // parent => [child => qty]
        $categories = [];
        foreach ($rows as $row) {
            $categories[(int) $row->child_category_id] = true;
            $categories[(int) $row->parent_category_id] = true;
            if ((int) $row->parent_category_id !== (int) $row->child_category_id) {
                $childOf[(int) $row->parent_category_id][(int) $row->child_category_id] = (float) $row->quantity;
            }
        }

        // Base unit = a category that never acts as a parent of a real link.
        $base = null;
        foreach (array_keys($categories) as $categoryId) {
            if (! isset($childOf[$categoryId])) {
                $base = $categoryId;
                break;
            }
        }

        // Walk each category down its chain to the base, multiplying quantities.
        $units = [];
        foreach (array_keys($categories) as $categoryId) {
            $multiplier = 1.0;
            $current = $categoryId;
            $guard = 0;
            while (isset($childOf[$current]) && $guard++ < 50) {
                $child = array_key_first($childOf[$current]);
                $multiplier *= $childOf[$current][$child];
                $current = $child;
            }
            $units[$categoryId] = $multiplier;
        }

        return $this->cache[$productId] = ['base' => $base, 'units' => $units];
    }
}
