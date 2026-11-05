<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Models\Farmula;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/** Formulas list (Inertia, Roadmap step 5): everything lives in MasterDataController. */
class FarmulaController extends MasterDataController
{
    protected function definition(): array
    {
        return [
            'model' => Farmula::class,
            'route' => 'farmulas',
            'wording' => ['formula', 'formulas'],
            'icon' => 'eyedropper',
            'accent' => 'var(--chart-5)',
            'fk' => 'farmula_id',
            'max' => 200,
            'help' => 'master-data',
            'examples' => 'e.g. Paracetamol, Amoxicillin',
        ];
    }

    protected function managePermission(): string
    {
        return 'pharmacy.masterdata.manage';
    }

    /** products.farmula_id holds a comma-separated list (a product can have several), so match inside the list. */
    protected function usageSubquery(string $idColumn): Builder
    {
        return Product::query()->selectRaw('count(*)')->whereRaw("FIND_IN_SET({$idColumn}, REPLACE(products.farmula_id, ' ', '')) > 0");
    }
}
