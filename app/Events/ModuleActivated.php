<?php

namespace App\Events;

use App\Models\Business;
use Illuminate\Foundation\Events\Dispatchable;

/** A module was switched on for a business: listeners seed that module's per-business defaults. */
class ModuleActivated
{
    use Dispatchable;

    public function __construct(public readonly Business $business, public readonly string $module)
    {
    }
}
