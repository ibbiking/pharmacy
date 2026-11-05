<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\MasterData\MasterDataController;
use App\Models\Strength;

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
}
