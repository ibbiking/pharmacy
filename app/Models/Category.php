<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'description', 'parent_category_id'];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }

    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    public static function buildChildren($parentId, $productId)
    {
        return \App\Models\ProductCategory::with('childCategory')
            ->where('parent_category_id', $parentId)
            ->where('product_id', $productId) // 🔑 ensure only this product
            ->get()
            ->map(function ($pc) use ($productId) {
                return [
                    'id' => $pc->childCategory->id,
                    'name' => $pc->childCategory->name,
                    'children' => self::buildChildren($pc->childCategory->id, $productId), // recursion with product
                ];
            });
    }
}
