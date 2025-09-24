<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseStock extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_stock';

    protected $fillable = ['purchase_id', 'product_id', 'base_category_id', 'current_stock'];
    
}