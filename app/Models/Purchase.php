<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'product_id','category_id','supplier_id','unit_cost_price', 'total_cost_price', 'unit_cost_tax_amount', 'total_cost_tax_amount',
        'quantity', 'base_category_id', 'base_quantity', 'unit_sale_price', 'total_sale_price', 'unit_sale_tax_amount', 'total_sale_tax_amount',
        'paid_unit_cost_price','extra_paid_per_unit','extra_paid_percent','paid_extra_total_cost_price',
        'base_unit_purchase_price', 'base_unit_purchase_tax_price', 'base_unit_total_purchase_tax_price', 
        'base_unit_sale_price', 'base_unit_sale_tax_price', 'base_unit_total_sale_tax_price', 
        'batch_no', 'invoice_no', 'expiry_date', 'image'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function taxes()
    {
        return $this->hasMany(PurchaseTax::class);
    }

    public function Saletaxes()
    {
        return $this->hasMany(SaleTax::class);
    }
}
