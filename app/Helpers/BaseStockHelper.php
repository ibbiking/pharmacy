<?php

use App\Models\ProductParameter;

if (!function_exists('calculateBaseStock')) {

    /**
     * Convert any category quantity to base category quantity
     *
     * @param int $productId
     * @param int $selectedCategoryId
     * @param float|int $quantity
     * @return array [baseCategoryId, baseQuantity]
     */
    function calculateBaseStock($productId, $selectedCategoryId, $quantity)
    {
        $params = ProductParameter::where('product_id', $productId)->get();

        // Build lookup: parent → [child => qty]
        $map = [];

        foreach ($params as $p) {
            // Skip self-row (base category row)
            if ($p->parent_category_id == $p->child_category_id) {
                continue;
            }

            $map[$p->parent_category_id][$p->child_category_id] = $p->quantity;
        }

        $currentCategory = $selectedCategoryId;
        $currentQty      = $quantity;

        // Traverse until no child exists
        while (isset($map[$currentCategory])) {
            $childId    = array_key_first($map[$currentCategory]);
            $multiplier = $map[$currentCategory][$childId];

            $currentQty = $currentQty * $multiplier;
            $currentCategory = $childId;
        }

        // Final base category & quantity
        return [$currentCategory, $currentQty];
    }
}