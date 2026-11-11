<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['name', 'code', 'address', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];
}
