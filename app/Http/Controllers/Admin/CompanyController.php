<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Models\Company;

/** Companies list (Inertia, Roadmap step 5): everything lives in MasterDataController. */
class CompanyController extends MasterDataController
{
    protected function definition(): array
    {
        return [
            'model' => Company::class,
            'route' => 'companies',
            'wording' => ['company', 'companies'],
            'icon' => 'building',
            'accent' => 'var(--chart-2)',
            'fk' => 'company_id',
            'max' => 100,
            'help' => 'master-data',
            'examples' => 'e.g. GSK, Getz Pharma, Samsung',
        ];
    }
}
