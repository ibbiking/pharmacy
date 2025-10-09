<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Preference;
use App\Models\ProductPreference;

class SalePricePreferenceSeeder extends Seeder
{
    public function run(): void
    {
        $preferences = [
            [
                'preference' => 'Static Sale Price',
                'slug'       => 'static-price',
            ],
            [
                'preference' => 'Stock wise Sale Price',
                'slug'       => 'stock-wise-price',
            ],
            [
                'preference' => 'Last/previous inventory Sale Price',
                'slug'       => 'previous-inventory-price',
            ],
            [
                'preference' => 'Sale Price Including tax',
                'slug'       => 'sale-price-including-tax',
            ],
        ];

        foreach ($preferences as $pref) {
            Preference::firstOrCreate(
                [
                    'type'       => 'sale_price',
                    'slug'       => $pref['slug'],
                ],
                [
                    'preference' => $pref['preference'],
                ]
            );
        }

        $productPreferences = [
            [
                'preference' => 'Static Sale Price',
                'slug'       => 'static-price',
            ],
            [
                'preference' => 'Stock wise Sale Price',
                'slug'       => 'stock-wise-price',
            ],
            [
                'preference' => 'Last/previous inventory Sale Price',
                'slug'       => 'previous-inventory-price',
            ],
        ];

        foreach ($productPreferences as $pPref) {
            ProductPreference::firstOrCreate(
                [
                    'type'       => 'sale_price',
                    'slug'       => $pPref['slug'],
                ],
                [
                    'preference' => $pPref['preference'],
                ]
            );
        }
    }
}
