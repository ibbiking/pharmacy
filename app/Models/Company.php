<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory,SoftDeletes;

    protected $fillable = ['name', 'description'];
}
