<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'product_name', 'description',
    ];

    public function purchase(){
        return $this->belongsTo(Purchase::class);
    }

    public function parameters()
{
    return $this->hasMany(ProductParameter::class);
}
}
