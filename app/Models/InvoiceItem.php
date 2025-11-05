<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'product_id',
        'name',
        'qty',
        'price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}