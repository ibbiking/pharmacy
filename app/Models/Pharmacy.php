<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacy extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    use \App\Traits\BelongsToBusiness;

    use HasFactory;
    
    protected $fillable = ['name', 'address', 'phone', 'note'];
}
