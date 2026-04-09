<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory,SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_no',
        'subtotal',
        'discount',
        'total',
        'invoice_discount_type',
        'invoice_discount_value',
        'invoice_discount_amount',
        'grand_total',
        'cash_received',
        'change_return',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function histories()
    {
        return $this->hasMany(InvoiceHistory::class);
    }

    public function returnHistories()
    {
        return $this->hasMany(ReturnHistory::class);
    }
}