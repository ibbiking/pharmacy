<?php

namespace App\Events;

use App\Models\Inventory\StockAdjustment;
use Illuminate\Foundation\Events\Dispatchable;

class StockAdjusted
{
    use Dispatchable;

    public function __construct(public StockAdjustment $adjustment)
    {
    }
}
