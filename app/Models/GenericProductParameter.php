<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GenericProductParameter extends Model
{
    use HasFactory, \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = [];

    public function parentCategory() { return $this->belongsTo(GenericCategory::class, 'parent_generic_category_id')->withTrashed(); }
    public function childCategory() { return $this->belongsTo(GenericCategory::class, 'child_generic_category_id')->withTrashed(); }
    public function genericCategory() { return $this->belongsTo(GenericCategory::class, 'generic_category_id')->withTrashed(); }

}
