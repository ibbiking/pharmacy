<?php

namespace App\Services\Sales;

/**
 * The effective sale rules for one product in one business, after applying
 * product override → business-wide preference → system default.
 */
final class SaleSettings
{
    public const PRICE_STATIC = 'static-price';
    public const PRICE_STOCK_WISE = 'stock-wise-price';
    public const PRICE_PREVIOUS = 'previous-inventory-price';
    public const PRICE_MODES = [self::PRICE_STATIC, self::PRICE_STOCK_WISE, self::PRICE_PREVIOUS];

    public const LOT_FEFO = 'fefo'; // earliest expiry first
    public const LOT_FIFO = 'fifo'; // oldest purchase first
    public const LOT_MODES = [self::LOT_FEFO, self::LOT_FIFO];

    public function __construct(
        public readonly string $priceMode,
        /** true = named Sale Tax is inside the price (owner-only visibility); false = added on top */
        public readonly bool $includeTax,
        public readonly string $lotMode,
        public readonly bool $allowPriceChange,
        /** lowest allowed charged price as % of the system price; null = no floor */
        public readonly ?float $minPricePercent,
        /** product-level discount cap (only when the product locks its max discount); null/0 = no cap */
        public readonly ?float $productMaxDiscountPercent,
        public readonly ?float $productMaxDiscountAmount,
        /** business-wide caps; null = no cap */
        public readonly ?float $maxLineDiscountPercent,
        public readonly ?float $maxInvoiceDiscountPercent,
        /** where the price mode came from: product | business | default */
        public readonly string $priceModeSource,
    ) {
    }
}
