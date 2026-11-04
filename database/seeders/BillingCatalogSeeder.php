<?php

namespace Database\Seeders;

use App\Models\ModulePrice;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Idempotent starting catalogue: a price for every sellable module and four starter plans.
 *
 * PRICES ARE PLACEHOLDERS: the super admin sets real prices in Plans & pricing. Existing rows are
 * never overwritten (firstOrCreate), so re-running this never undoes the super admin's edits.
 */
class BillingCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $prices = [
            'stock' => [15, 150], 'pos' => [15, 150], 'pharmacy' => [10, 100], 'inventory_pro' => [20, 200],
            'b2b_sales' => [25, 250], 'b2b_purchase' => [20, 200], 'accounting' => [25, 250],
            'expense' => [15, 150], 'analytics' => [15, 150], 'fbr' => [10, 100],
        ];
        foreach ($prices as $module => [$monthly, $yearly]) {
            ModulePrice::firstOrCreate(['module' => $module], ['monthly_price' => $monthly, 'yearly_price' => $yearly, 'currency' => 'USD']);
        }

        $plans = [
            ['slug' => 'free-launch', 'name' => 'Free Launch', 'description' => 'Everything, free during launch.',
                'modules' => array_keys($prices), 'monthly' => 0, 'yearly' => 0, 'visible' => false, 'order' => 0, 'limits' => null],
            ['slug' => 'pharmacy-starter', 'name' => 'Pharmacy Starter', 'description' => 'Stock, POS and the pharmacy pack for one pharmacy.',
                'modules' => ['stock', 'pos', 'pharmacy'], 'monthly' => 29, 'yearly' => 290, 'visible' => true, 'order' => 1,
                'limits' => ['users' => 5, 'businesses' => 1, 'products' => null, 'invoices_per_month' => null, 'warehouses' => 1]],
            ['slug' => 'inventory-pro', 'name' => 'Inventory Pro', 'description' => 'Stock, POS and multi-warehouse inventory.',
                'modules' => ['stock', 'pos', 'inventory_pro'], 'monthly' => 39, 'yearly' => 390, 'visible' => true, 'order' => 2,
                'limits' => ['users' => 10, 'businesses' => 2, 'products' => null, 'invoices_per_month' => null, 'warehouses' => 5]],
            ['slug' => 'b2b-complete', 'name' => 'B2B Complete', 'description' => 'Everything for wholesale and distribution, with books and expenses.',
                'modules' => ['stock', 'pos', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense'], 'monthly' => 99, 'yearly' => 990, 'visible' => true, 'order' => 3,
                'limits' => null],
        ];
        foreach ($plans as $p) {
            Plan::withTrashed()->firstOrCreate(['slug' => $p['slug']], [
                'name' => $p['name'], 'description' => $p['description'], 'modules' => $p['modules'],
                'monthly_price' => $p['monthly'], 'yearly_price' => $p['yearly'], 'currency' => 'USD',
                'limits' => $p['limits'], 'is_visible' => $p['visible'], 'sort_order' => $p['order'],
            ]);
        }
    }
}
