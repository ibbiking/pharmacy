<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItemReturn extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory;

    protected $fillable = [
        'return_no',
        'invoice_id',
        'invoice_item_id',
        'product_id',
        'qty_returned',
        'unit_discount_deducted',
        'global_discount_clawback',
        'reason',
        'handled_by',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}