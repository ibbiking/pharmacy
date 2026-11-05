<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Models\ProductType;

/** Product Types list (Inertia, Roadmap step 5): everything lives in MasterDataController. */
class ProductTypeController extends MasterDataController
{
    protected function definition(): array
    {
        return [
            'model' => ProductType::class,
            'route' => 'product-types',
            'wording' => ['product_type', 'product_types'],
            'icon' => 'capsule',
            'accent' => 'var(--chart-6)',
            'fk' => 'product_type_id',
            'max' => 100,
            'help' => 'master-data',
            'examples' => 'e.g. Tablet, Syrup, Injection',
        ];
    }
}
