<?php

namespace App\Services\Sales;

use App\Models\BaseStockSalePrice;
use App\Models\ProductParameter;

/**
 * The system ("server") sale price of one unit of a product, per the sale-price preference:
 *
 *   static     → the fixed sale price configured for that packaging unit in the product setup
 *   stock-wise → the price of the lot being sold (default: first sellable lot in FEFO/FIFO order)
 *   previous   → the price of the most recently purchased lot (even if sold out)
 *
 * Lot prices are stored per BASE unit and converted with PackagingService. When the preference
 * says "including tax", the lot's sale tax per base unit is part of the price (decision D3).
 */
class SalePriceCalculator
{
    public function __construct(
        private readonly PackagingService $packaging,
        private readonly LotAllocator $lots,
    ) {
    }

    public function unitPrice(int $productId, ?int $categoryId, SaleSettings $settings, ?BaseStockSalePrice $lot = null): float
    {
        $categoryId ??= $this->packaging->baseCategoryId($productId);

        switch ($settings->priceMode) {
            case SaleSettings::PRICE_STOCK_WISE:
                $lot ??= $this->lots->sellableLots($productId, $settings->lotMode)->first();

                return $lot ? $this->lotUnitPrice($lot, $categoryId, $settings->includeTax) : 0.0;

            case SaleSettings::PRICE_PREVIOUS:
                $latest = BaseStockSalePrice::query()
                    ->where('product_id', $productId)
                    ->orderByDesc('created_at')->orderByDesc('id')
                    ->first();

                return $latest ? $this->lotUnitPrice($latest, $categoryId, $settings->includeTax) : 0.0;

            case SaleSettings::PRICE_STATIC:
            default:
                return $this->staticPrice($productId, $categoryId);
        }
    }

    public function staticPrice(int $productId, ?int $categoryId): float
    {
        return round((float) (ProductParameter::query()
            ->where('product_id', $productId)
            ->where('child_category_id', $categoryId)
            ->value('static_category_unit_sale_price') ?? 0), 2);
    }

    /** Price of one $categoryId unit sold from $lot. */
    public function lotUnitPrice(BaseStockSalePrice $lot, ?int $categoryId, bool $includeTax): float
    {
        $basePrice = (float) $lot->base_category_unit_sale_price
            + ($includeTax ? (float) ($lot->base_category_unit_sale_tax_price ?? 0) : 0.0);

        return round($basePrice * $this->packaging->baseUnitsPer((int) $lot->product_id, $categoryId), 2);
    }
}
