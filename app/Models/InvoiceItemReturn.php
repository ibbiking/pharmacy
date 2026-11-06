<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItemReturn extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

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
        'price',
        'refund_amount',
        'taxes',
    ];

    /** Snapshot of what this return was (Roadmap 5.8a): taxes = [{tax_id, name, rate, amount, kind}] refunded/disclosed. */
    protected $casts = [
        'qty_returned' => 'float',
        'price' => 'float',
        'refund_amount' => 'float',
        'taxes' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Who processed the return. */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
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