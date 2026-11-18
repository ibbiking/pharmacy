<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Model;

/** A package / shipment of a sales order or sales invoice (Wholesale & Distribution; no courier API). */
class Shipment extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const STATUSES = ['packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];

    protected $fillable = ['document_id', 'number', 'carrier', 'tracking_no', 'packages', 'weight_kg', 'status', 'shipped_at', 'delivered_at', 'notes', 'created_by'];

    protected $casts = ['packages' => 'integer', 'weight_kg' => 'float', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function document() { return $this->belongsTo(Document::class)->withTrashed(); }
}
