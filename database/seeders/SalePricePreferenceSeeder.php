<?php

namespace Database\Seeders;

use App\Models\ProductPreference;
use App\Services\Business\BusinessPreferenceDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent. Seeds:
 *  - the global (business_id = NULL) product-level sale price options products can pick from;
 *  - the business-wide preference rows for every existing business.
 *
 * Previously this created business-wide `preferences` rows without a business (business_id NULL
 * on a fresh install, invisible to every business). Business-wide rows are now always created per
 * business by BusinessPreferenceDefaults (also run automatically when a business is created).
 */
class SalePricePreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $productPreferences = [
            ['preference' => 'Static Sale Price', 'slug' => 'static-price'],
            ['preference' => 'Stock wise Sale Price', 'slug' => 'stock-wise-price'],
            ['preference' => 'Last/previous inventory Sale Price', 'slug' => 'previous-inventory-price'],
            ['preference' => 'Sale Price Including tax', 'slug' => 'sale-price-including-tax'],
        ];

        foreach ($productPreferences as $pPref) {
            ProductPreference::firstOrCreate(
                ['type' => 'sale_price', 'slug' => $pPref['slug']],
                ['preference' => $pPref['preference']]
            );
        }

        $defaults = app(BusinessPreferenceDefaults::class);
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $defaults->ensure((int) $businessId);
        }
    }
}
