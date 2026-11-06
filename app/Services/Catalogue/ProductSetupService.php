<?php

namespace App\Services\Catalogue;

use App\Models\BaseStockSalePrice;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductParameter;
use App\Models\Purchase;
use App\Services\Sales\PackagingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A product's packaging chain and unit prices (the setup wizard, Roadmap 5.4c). One place for the rules:
 *  - the chain is a list of units, largest first (Box → Strip → Tablet); each level after the first says how
 *    many of it fit in the level above; the last level is the base unit all stock is counted in;
 *  - units must be different and belong to this business; quantities are > 0;
 *  - once the product has purchases, stock or sales the chain and quantities are LOCKED (existing batches were
 *    converted with them); only the static prices can change;
 *  - static purchase price must be lower than the static sale price when both are set;
 *  - saving a complete chain turns a draft into a real product.
 * Storage is unchanged for the sale engine: ProductParameter rows (root "self" row + one row per link) and the
 * matching ProductCategory relations.
 */
class ProductSetupService
{
    public function __construct(private readonly PackagingService $packaging)
    {
    }

    /** @return array<int, array{category_id: int, name: string, quantity: float, purchase_price: ?float, sale_price: ?float, base_units: float}> */
    public function chain(Product $product): array
    {
        $params = ProductParameter::query()->where('product_id', $product->id)->get();
        $root = $params->first(fn ($p) => (int) $p->parent_category_id === (int) $p->child_category_id);
        if (! $root) {
            return [];
        }

        $levels = [$root];
        $current = (int) $root->child_category_id;
        $guard = 0;
        while ($guard++ < 20) {
            $next = $params->first(fn ($p) => (int) $p->parent_category_id === $current && (int) $p->child_category_id !== $current);
            if (! $next) {
                break;
            }
            $levels[] = $next;
            $current = (int) $next->child_category_id;
        }

        $names = Category::withTrashed()->whereIn('id', collect($levels)->pluck('child_category_id'))->pluck('name', 'id');
        $this->packaging->forget($product->id);

        return collect($levels)->map(fn ($p, $i) => [
            'category_id' => (int) $p->child_category_id,
            'name' => $names[$p->child_category_id] ?? '?',
            'quantity' => $i === 0 ? 1.0 : (float) $p->quantity,
            'purchase_price' => $p->static_category_unit_purchase_price !== null ? (float) $p->static_category_unit_purchase_price : null,
            'sale_price' => $p->static_category_unit_sale_price !== null ? (float) $p->static_category_unit_sale_price : null,
            'base_units' => $this->safeBaseUnits($product->id, (int) $p->child_category_id),
        ])->values()->all();
    }

    /** Why the chain can no longer change, or null when it can. */
    public function lockReason(Product $product): ?string
    {
        return match (true) {
            Purchase::query()->where('product_id', $product->id)->exists() => 'This product already has purchases.',
            BaseStockSalePrice::query()->where('product_id', $product->id)->exists() => 'This product already has stock.',
            InvoiceItem::query()->where('product_id', $product->id)->exists() => 'This product has already been sold.',
            default => null,
        };
    }

    /**
     * @param  array<int, array{category_id: int|string, quantity?: mixed, purchase_price?: mixed, sale_price?: mixed}>  $levels  largest unit first
     */
    public function save(Product $product, array $levels): void
    {
        $levels = array_values($levels);
        $errors = [];

        if ($levels === []) {
            throw ValidationException::withMessages(['levels' => 'Add at least one unit.']);
        }
        if (count($levels) > 6) {
            throw ValidationException::withMessages(['levels' => 'Use at most 6 packaging levels.']);
        }

        $ids = array_map(fn ($l) => (int) ($l['category_id'] ?? 0), $levels);
        if (count(array_unique($ids)) !== count($ids)) {
            $errors['levels'] = 'Each unit can appear only once in the chain.';
        }
        $known = Category::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all(); // business-scoped
        foreach ($ids as $i => $id) {
            if (! in_array($id, $known, true)) {
                $errors["levels.{$i}.category_id"] = 'Choose a unit from your packaging list.';
            }
        }

        foreach ($levels as $i => $l) {
            $qty = $i === 0 ? 1 : $l['quantity'] ?? null;
            if (! is_numeric($qty) || (float) $qty <= 0 || (float) $qty > 100000) {
                $errors["levels.{$i}.quantity"] = 'Enter how many fit in the unit above (more than 0).';
            }
            foreach (['purchase_price', 'sale_price'] as $field) {
                $v = $l[$field] ?? null;
                if ($v !== null && $v !== '' && (! is_numeric($v) || (float) $v < 0 || (float) $v > 100000000)) {
                    $errors["levels.{$i}.{$field}"] = 'Enter a price of 0 or more.';
                }
            }
            $buy = (float) ($l['purchase_price'] ?? 0);
            $sell = (float) ($l['sale_price'] ?? 0);
            if ($buy > 0 && $sell > 0 && $buy >= $sell) {
                $errors["levels.{$i}.sale_price"] = 'The sale price must be higher than the purchase price.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $current = $this->chain($product);
        if ($reason = $this->lockReason($product)) {
            $same = count($current) === count($levels);
            foreach ($levels as $i => $l) {
                $same = $same && isset($current[$i]) && $current[$i]['category_id'] === (int) $l['category_id']
                    && ($i === 0 || abs($current[$i]['quantity'] - (float) $l['quantity']) < 0.000001);
            }
            if (! $same) {
                throw ValidationException::withMessages(['levels' => "{$reason} Its packaging is locked so existing stock stays correct; you can still change the prices."]);
            }
        }

        DB::transaction(function () use ($product, $levels) {
            $root = (int) $levels[0]['category_id'];
            $price = fn ($v) => ($v === null || $v === '') ? null : round((float) $v, 4);

            ProductParameter::query()->where('product_id', $product->id)->get()->each->delete();
            ProductCategory::query()->where('product_id', $product->id)->get()->each->delete();

            foreach ($levels as $i => $l) {
                $child = (int) $l['category_id'];
                $parent = $i === 0 ? $child : (int) $levels[$i - 1]['category_id'];
                ProductParameter::create([
                    'product_id' => $product->id,
                    'category_id' => $root,
                    'parent_category_id' => $parent,
                    'child_category_id' => $child,
                    'quantity' => $i === 0 ? 1 : (float) $l['quantity'],
                    'static_category_unit_purchase_price' => $price($l['purchase_price'] ?? null),
                    'static_category_unit_sale_price' => $price($l['sale_price'] ?? null),
                ]);
                if ($i > 0 || count($levels) === 1) {
                    ProductCategory::create(['product_id' => $product->id, 'parent_category_id' => $parent, 'child_category_id' => $child]);
                }
            }

            if ($product->is_draft) {
                $product->update(['is_draft' => false]);
            }
        });

        $this->packaging->forget($product->id);
        \App\Services\GenericProductService::syncParametersToGeneric($product->fresh());
    }

    private function safeBaseUnits(int $productId, int $categoryId): float
    {
        try {
            return $this->packaging->baseUnitsPer($productId, $categoryId);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
