<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockTransferLot extends Model
{
    use \App\Traits\BelongsToBusiness;
    // Append-only movement record: archived together with its document.

    protected $fillable = ['stock_transfer_line_id', 'from_lot_id', 'to_lot_id', 'base_qty'];

    protected $casts = ['base_qty' => 'float'];
}
