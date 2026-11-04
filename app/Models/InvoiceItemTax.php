<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItemTax extends Model
{
    use \App\Traits\BelongsToBusiness;
    use HasFactory;

    protected $table = 'invoice_item_taxes';

    protected $casts = [
        'is_included' => 'boolean',
    ];

    protected $fillable = [
        'invoice_item_id',
        'tax_id',
        'name',
        'rate',
        'amount',
        'is_included',
    ];

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }
}
