<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseTax extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['purchase_id', 'product_id', 'tax_id', 'tax_rate', 'tax_unit_amount', 'tax_amount'];

    public function tax()
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}