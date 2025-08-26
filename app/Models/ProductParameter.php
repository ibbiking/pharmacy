<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductParameter extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'product_id',
        'category_id',
        'parent_category_id',
        'child_category_id',
        'quantity',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function childCategory()
    {
        return $this->belongsTo(Category::class, 'child_category_id');
    }
}
