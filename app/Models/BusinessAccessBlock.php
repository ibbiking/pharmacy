<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A module or sub-module (permission) blocked for one business by the super admin. Not business-scoped on purpose. */
class BusinessAccessBlock extends Model
{
    protected $fillable = ['business_id', 'key', 'reason', 'blocked_by'];
}
