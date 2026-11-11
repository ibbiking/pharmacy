<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockTransferLine extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['stock_transfer_id', 'product_id', 'category_id', 'qty', 'base_qty'];

    protected $casts = ['qty' => 'float', 'base_qty' => 'float'];

    public function product() { return $this->belongsTo(\App\Models\Product::class)->withTrashed(); }
    public function unit() { return $this->belongsTo(\App\Models\Category::class, 'category_id')->withTrashed(); }
    public function lots() { return $this->hasMany(StockTransferLot::class); }
}
