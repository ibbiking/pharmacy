<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id','category_id','supplier_id',
        'unit_cost_price','quantity','expiry_date','total_cost_price','unit_cost_tax_amount','total_cost_tax_amount','batch_no',
        'image'
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
