<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLot extends Model
{
    use \App\Traits\BelongsToBusiness;
    // Append-only movement record: archived together with its document.

    protected $fillable = ['stock_adjustment_line_id', 'lot_id', 'purchase_id', 'base_qty', 'base_unit_cost'];

    protected $casts = ['base_qty' => 'float', 'base_unit_cost' => 'float'];
}
