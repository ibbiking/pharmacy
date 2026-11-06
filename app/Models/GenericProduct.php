<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GenericProduct extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = [];

    /** Names stay readable when an entry was archived later. */
    public function genericCompany() { return $this->belongsTo(GenericCompany::class)->withTrashed(); }
    public function genericType() { return $this->belongsTo(GenericProductType::class, 'generic_product_type_id')->withTrashed(); }
    public function parameters() { return $this->hasMany(GenericProductParameter::class, 'generic_product_id'); }

}
