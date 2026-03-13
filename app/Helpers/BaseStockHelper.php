<?php

namespace App\Helpers;

use App\Models\ProductParameter;
use App\Models\BaseStockSalePrice;
use Carbon\Carbon;

class BaseStockHelper
{
    /**
     * Calculate sale price based on preference slug
     */
    public static function calculateSalePrice($productId, $selectedCategoryId, $preferenceInfo)
    {
        $preference = $preferenceInfo['preference'];
        $includingTax = $preferenceInfo['including_tax'] ?? false;

        switch ($preference->slug) {
            case 'static-price':
                return self::getStaticPrice($productId, $selectedCategoryId, $includingTax);

            case 'stock-wise-price':
                return self::getStockWisePrice($productId, $selectedCategoryId, $includingTax);

            case 'previous-inventory-price':
                return self::getPreviousInventoryPrice($productId, $selectedCategoryId, $includingTax);

            default:
                return self::getStaticPrice($productId, $selectedCategoryId, $includingTax);
        }
    }

    public static function getStaticPrice($productId, $selectedCategoryId, $includingTax)
    {
        $parameter = ProductParameter::where('product_id', $productId)
            ->where('child_category_id', $selectedCategoryId)
            ->first();

        return $parameter->static_category_unit_sale_price ?? 0;
    }

    public static function getStockWisePrice($productId, $selectedCategoryId, $includingTax)
    {
        $baseStockPrice = BaseStockSalePrice::where('product_id', $productId)
            ->where('remaining_base_stock', '>', 0)
            ->where('expiry_date', '>=', Carbon::now())
            ->orderBy('id', 'asc')
            ->first();

        if (!$baseStockPrice) return 0;

        $basePrice = $includingTax 
            ? ($baseStockPrice->base_category_unit_sale_price + ($baseStockPrice->base_category_unit_sale_tax_price ?? 0))
            : $baseStockPrice->base_category_unit_sale_price;

        // If selected category is base category, return base price
        if ($selectedCategoryId == $baseStockPrice->base_category_id) return $basePrice;

        // Otherwise, adjust price using ProductParameter quantities
        return self::calculateCategoryPrice($productId, $selectedCategoryId, $baseStockPrice->base_category_id, $basePrice);
    }

    public static function getPreviousInventoryPrice($productId, $selectedCategoryId, $includingTax)
    {
        $baseStockPrice = BaseStockSalePrice::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$baseStockPrice) return 0;

        $basePrice = $includingTax 
            ? ($baseStockPrice->base_category_unit_sale_price + ($baseStockPrice->base_category_unit_sale_tax_price ?? 0))
            : $baseStockPrice->base_category_unit_sale_price;

        if ($selectedCategoryId == $baseStockPrice->base_category_id) return $basePrice;

        return self::calculateCategoryPrice($productId, $selectedCategoryId, $baseStockPrice->base_category_id, $basePrice);
    }

    /**
     * Convert base category price to selected category using parameters
     */
    public static function calculateCategoryPrice($productId, $selectedCategoryId, $baseCategoryId, $basePrice)
    {
        $parameter = ProductParameter::where('product_id', $productId)
            ->where('child_category_id', $selectedCategoryId)
            ->first();

        if (!$parameter) return $basePrice;

        // Simple proportional calculation
        return $basePrice * ($parameter->quantity ?? 1);
    }
}