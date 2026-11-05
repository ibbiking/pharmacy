<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * One lot (purchase batch) an invoice line consumed, with the quantity and its cost.
 * Written at checkout; used by returns (restock the exact lot) and profit/loss (actual cost).
 */
class InvoiceItemLot extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    use BelongsToBusiness;

    protected $guarded = [];

    protected $casts = [
        'base_qty' => 'float',
        'returned_base_qty' => 'float',
        'base_unit_cost' => 'float',
        'base_unit_purchase_tax' => 'float',
        'base_unit_sale_price' => 'float',
    ];

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function lot()
    {
        return $this->belongsTo(BaseStockSalePrice::class, 'base_stock_sale_price_id');
    }

    public function netBaseQty(): float
    {
        return max(0, $this->base_qty - $this->returned_base_qty);
    }
}
