<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GenericProduct extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function genericCompany() { return $this->belongsTo(GenericCompany::class); }
    public function genericType() { return $this->belongsTo(GenericProductType::class, 'generic_product_type_id'); }
    public function parameters() { return $this->hasMany(GenericProductParameter::class, 'generic_product_id'); }

}
