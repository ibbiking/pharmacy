<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory,SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_no',
        'subtotal',
        'discount',
        'total',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}