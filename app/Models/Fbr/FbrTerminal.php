<?php

namespace App\Models\Fbr;

use Illuminate\Database\Eloquent\Model;

/** A counter registered on IRIS (FBR POS Integration): outlet name + POS ID, per environment. */
class FbrTerminal extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['outlet_name', 'pos_id', 'environment', 'warehouse_id', 'is_default'];

    protected $casts = ['is_default' => 'boolean', 'warehouse_id' => 'integer'];
}
