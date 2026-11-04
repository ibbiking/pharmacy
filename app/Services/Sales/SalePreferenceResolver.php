<?php

namespace App\Services\Sales;

use App\Models\Preference;
use App\Models\Product;
use App\Models\ProductPreference;

/**
 * Resolves the effective sale rules (SaleSettings) for a product:
 *   product override  →  business-wide preference  →  system default.
 *
 * Business-wide rows live in `preferences` (one set per business, created by
 * BusinessPreferenceDefaults). Product overrides live on `products` (NULL = inherit).
 */
class SalePreferenceResolver
{
    /** @var array<int, \Illuminate\Support\Collection> businessId => preferences keyed "type:slug" */
    private array $businessRows = [];

    /** @var array<int, ?string> productPreferenceId => slug */
    private array $productPreferenceSlugs = [];

    public function forProduct(Product|int $product): SaleSettings
    {
        if (! $product instanceof Product) {
            $product = Product::withoutGlobalScopes()->findOrFail($product);
        }

        $rows = $this->businessRows((int) $product->business_id);

        // Price mode + tax inclusion: a product-level choice wins as a pair.
        $priceMode = null;
        $includeTax = false;
        $source = 'default';

        $productSlug = $this->productPreferenceSlug($product->sale_price_preference_id);
        if ($productSlug !== null && in_array($productSlug, SaleSettings::PRICE_MODES, true)) {
            $priceMode = $productSlug;
            $includeTax = (bool) $product->sale_price_including_tax;
            $source = 'product';
        } else {
            foreach (SaleSettings::PRICE_MODES as $mode) {
                if ($this->isOn($rows, 'sale_price', $mode)) {
                    $priceMode = $mode;
                    $source = 'business';
                    break;
                }
            }
            $includeTax = $this->isOn($rows, 'sale_price', 'sale-price-including-tax');
        }
        $priceMode ??= SaleSettings::PRICE_STATIC;

        $lotMode = in_array($product->lot_selection_mode, SaleSettings::LOT_MODES, true)
            ? $product->lot_selection_mode
            : ($this->isOn($rows, 'lot_selection', SaleSettings::LOT_FIFO) && ! $this->isOn($rows, 'lot_selection', SaleSettings::LOT_FEFO)
                ? SaleSettings::LOT_FIFO
                : SaleSettings::LOT_FEFO);

        $allowPriceChange = $product->allow_price_change !== null
            ? (bool) $product->allow_price_change
            : ($rows->has('pos:allow-price-change') ? $this->isOn($rows, 'pos', 'allow-price-change') : true);

        $minPricePercent = $product->min_price_percent !== null
            ? (float) $product->min_price_percent
            : $this->numericValue($rows, 'pos', 'min-price-percent');

        $productMaxPercent = null;
        $productMaxAmount = null;
        if ($product->lock_max_discount) {
            // Mirrors the POS screen: a 0 cap means "no cap" for that discount type.
            $productMaxPercent = (float) $product->discount_percent > 0 ? (float) $product->discount_percent : null;
            $productMaxAmount = (float) $product->discount > 0 ? (float) $product->discount : null;
        }

        return new SaleSettings(
            priceMode: $priceMode,
            includeTax: $includeTax,
            lotMode: $lotMode,
            allowPriceChange: $allowPriceChange,
            minPricePercent: $minPricePercent,
            productMaxDiscountPercent: $productMaxPercent,
            productMaxDiscountAmount: $productMaxAmount,
            maxLineDiscountPercent: $this->numericValue($rows, 'pos', 'max-line-discount-percent'),
            maxInvoiceDiscountPercent: $this->numericValue($rows, 'pos', 'max-invoice-discount-percent'),
            priceModeSource: $source,
        );
    }

    /** Business-wide caps that are not tied to one product (invoice discount). */
    public function maxInvoiceDiscountPercent(int $businessId): ?float
    {
        return $this->numericValue($this->businessRows($businessId), 'pos', 'max-invoice-discount-percent');
    }

    public function forget(): void
    {
        $this->businessRows = [];
        $this->productPreferenceSlugs = [];
    }

    private function businessRows(int $businessId)
    {
        return $this->businessRows[$businessId] ??= Preference::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy(fn ($row) => $row->type.':'.$row->slug);
    }

    private function productPreferenceSlug($productPreferenceId): ?string
    {
        if (! $productPreferenceId) {
            return null;
        }

        if (! array_key_exists($productPreferenceId, $this->productPreferenceSlugs)) {
            $this->productPreferenceSlugs[$productPreferenceId] =
                ProductPreference::withoutGlobalScopes()->whereKey($productPreferenceId)->value('slug');
        }

        return $this->productPreferenceSlugs[$productPreferenceId];
    }

    private function isOn($rows, string $type, string $slug): bool
    {
        return (bool) optional($rows->get($type.':'.$slug))->status;
    }

    /** Value of a switchable numeric preference; null when switched off or not numeric. */
    private function numericValue($rows, string $type, string $slug): ?float
    {
        $row = $rows->get($type.':'.$slug);

        return ($row && $row->status && is_numeric($row->value)) ? (float) $row->value : null;
    }
}
