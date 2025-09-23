<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseTax extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['purchase_id', 'product_id', 'tax_id', 'tax_rate', 'tax_amount'];
}