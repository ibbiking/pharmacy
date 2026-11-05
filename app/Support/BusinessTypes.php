<?php

namespace App\Support;

use App\Services\Modules\ModuleManager;

/**
 * The business types a new business can be (shown as picture blocks on the landing page, at sign-up
 * and in the create-business wizard). The wording per type lives in config/business_types.php.
 */
class BusinessTypes
{
    public const TYPES = [
        'pharmacy' => [
            'name' => 'Pharmacy',
            'icon' => 'capsule-pill',
            'accent' => 'var(--chart-5)',
            'tagline' => 'Medical stores & chemists',
            'description' => 'Medicines with batches & expiry, generic medicine catalogue, POS with FEFO, FBR-ready receipts.',
            'examples' => ['Medical stores', 'Chemists', 'Hospital pharmacies'],
        ],
        'inventory' => [
            'name' => 'Inventory',
            'icon' => 'box-seam',
            'accent' => 'var(--chart-2)',
            'tagline' => 'Shops & retail stores',
            'description' => 'Shops and stores selling items: stock, purchases, POS, multi-warehouse (coming soon).',
            'examples' => ['Grocery & general stores', 'Electronics', 'Clothing & shoes', 'Hardware'],
        ],
        'inventory_b2b' => [
            'name' => 'Inventory B2B',
            'icon' => 'briefcase',
            'accent' => 'var(--chart-4)',
            'tagline' => 'Wholesale & distribution',
            'description' => 'Wholesale & distribution: quotations, sales/purchase orders, accounting and expenses (rolling out in stages).',
            'examples' => ['Distributors', 'Wholesalers', 'Importers', 'Manufacturers'],
        ],
    ];

    public static function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public static function exists(?string $type): bool
    {
        return $type !== null && isset(self::TYPES[$type]);
    }

    /** For screens: each type with its key, recommended module keys and their names. */
    public static function forScreens(): array
    {
        $modules = app(ModuleManager::class);

        return collect(self::TYPES)->map(function ($t, $key) use ($modules) {
            $bundle = $modules->bundleFor($key);

            return $t + [
                'key' => $key,
                'bundle' => $bundle,
                'bundle_names' => array_values(array_map(fn ($m) => $modules->definition($m)['name'], array_filter($bundle, fn ($m) => $m !== 'core' && $modules->exists($m)))),
            ];
        })->values()->all();
    }
}
