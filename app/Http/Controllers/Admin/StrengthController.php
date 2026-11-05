<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Models\Strength;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/** Strengths list (Inertia, Roadmap step 5): everything lives in MasterDataController. */
class StrengthController extends MasterDataController
{
    protected function definition(): array
    {
        return [
            'model' => Strength::class,
            'route' => 'strengths',
            'wording' => ['strength', 'strengths'],
            'icon' => 'speedometer',
            'accent' => 'var(--chart-4)',
            'fk' => 'strength_id',
            'max' => 150,
            'help' => 'master-data',
            'examples' => 'e.g. 500 mg, 250 mg/5 ml',
        ];
    }

    protected function managePermission(): string
    {
        return 'pharmacy.masterdata.manage';
    }

    /** products.strength_id holds a comma-separated list (a product can have several), so match inside the list. */
    protected function usageSubquery(string $idColumn): Builder
    {
        return Product::query()->selectRaw('count(*)')->whereRaw("FIND_IN_SET({$idColumn}, REPLACE(products.strength_id, ' ', '')) > 0");
    }
}
