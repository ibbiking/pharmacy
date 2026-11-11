<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLine extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['stock_adjustment_id', 'product_id', 'category_id', 'qty', 'base_qty', 'value'];

    protected $casts = ['qty' => 'float', 'base_qty' => 'float', 'value' => 'float'];

    public function product() { return $this->belongsTo(\App\Models\Product::class)->withTrashed(); }
    public function unit() { return $this->belongsTo(\App\Models\Category::class, 'category_id')->withTrashed(); }
    public function lots() { return $this->hasMany(StockAdjustmentLot::class); }
}
