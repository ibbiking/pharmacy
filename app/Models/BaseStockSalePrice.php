<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseStockSalePrice extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory, SoftDeletes;

    protected $table = 'base_stock_sale_price';

    protected $fillable = [
        'purchase_id',
         'product_id',
          'base_category_id',
           'category_id',
            'base_stock',
             'remaining_base_stock',
              'base_category_unit_sale_price',
               'base_category_unit_sale_tax_price',
                'expiry_date'
            ];
    
}