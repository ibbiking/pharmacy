<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['number', 'warehouse_id', 'date', 'direction', 'reason', 'note', 'value', 'created_by'];

    protected $casts = ['date' => 'date', 'value' => 'float'];

    public const REASONS = ['damaged' => 'Damaged', 'expired' => 'Expired', 'lost' => 'Lost / stolen', 'count' => 'Stock count', 'found' => 'Found stock', 'other' => 'Other'];

    public function lines() { return $this->hasMany(StockAdjustmentLine::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class)->withTrashed(); }
    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
