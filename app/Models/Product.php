<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_name',
        'company_id',
        'farmula_id',
        'product_type_id',
        'strength_id',
        'description',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function parameters()
    {
        return $this->hasMany(ProductParameter::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function farmula()
    {
        return $this->belongsTo(Farmula::class);
    }

    public function type()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function strength()
    {
        return $this->belongsTo(Strength::class, 'strength_id');
    }

}
