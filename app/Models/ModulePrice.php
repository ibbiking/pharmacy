<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModulePrice extends Model
{
    use \App\Traits\Auditable;

    protected $guarded = [];
    protected $casts = ['monthly_price' => 'float', 'yearly_price' => 'float'];
}
