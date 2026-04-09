<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockPrices extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory, SoftDeletes;

    protected $table = 'stock_prices';

    protected $fillable = [
        'purchase_id',
        'product_id',
        'category_id',
        'base_category_id',
        'base_stock',
        'category_stock',
        'category_unit_purchase_price',
        'category_unit_purchase_tax_price',
        'category_unit_sale_price',
        'category_unit_sale_tax_price',
        'base_category_unit_purchase_price',
        'base_category_unit_purchase_tax_price',
        'base_category_unit_sale_price',
        'base_category_unit_sale_tax_price',
        'base_category_unit_total_purchase_tax_price',
        'base_category_unit_total_sale_tax_price',
        'category_unit_total_purchase_tax_price',
        'category_unit_total_sale_tax_price',
    ];
    
}