<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Model;

class DocumentLine extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'document_id', 'product_id', 'category_id', 'source_line_id', 'description', 'qty', 'base_qty', 'unit_price', 'discount_percent', 'discount_amount',
        'tax_id', 'tax_rate', 'tax_amount', 'net_amount', 'line_total', 'done_qty', 'cost_amount', 'position',
    ];

    protected $casts = [
        'qty' => 'float', 'base_qty' => 'float', 'unit_price' => 'float', 'discount_percent' => 'float', 'discount_amount' => 'float', 'tax_rate' => 'float',
        'tax_amount' => 'float', 'net_amount' => 'float', 'line_total' => 'float', 'done_qty' => 'float', 'cost_amount' => 'float',
    ];

    public function document() { return $this->belongsTo(Document::class); }
    public function product() { return $this->belongsTo(\App\Models\Product::class)->withTrashed(); }
    public function unit() { return $this->belongsTo(\App\Models\Category::class, 'category_id')->withTrashed(); }
    public function tax() { return $this->belongsTo(\App\Models\Tax::class)->withTrashed(); }
    public function lots() { return $this->hasMany(DocumentLineLot::class); }
}
