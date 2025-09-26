<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id','category_id','supplier_id',
        'unit_cost_price','quantity','expiry_date','total_cost_price','unit_cost_tax_amount','total_cost_tax_amount','batch_no',
        'image', 'base_category_id', 'base_quantity', 'unit_sale_price', 'total_sale_price', 'total_sale_tax_amount', 'unit_sale_tax_amount'
    ];

    public function supplier(){
        return $this->belongsTo(Supplier::class);
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }
    
    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function taxes()
    {
        return $this->hasMany(PurchaseTax::class);
    }
}
