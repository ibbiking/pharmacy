<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['number', 'from_warehouse_id', 'to_warehouse_id', 'date', 'note', 'created_by'];

    protected $casts = ['date' => 'date'];

    public function lines() { return $this->hasMany(StockTransferLine::class); }
    public function from() { return $this->belongsTo(Warehouse::class, 'from_warehouse_id')->withTrashed(); }
    public function to() { return $this->belongsTo(Warehouse::class, 'to_warehouse_id')->withTrashed(); }
    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
