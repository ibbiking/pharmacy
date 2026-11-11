<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['name', 'company', 'email', 'phone', 'address', 'tax_number', 'payment_terms_days', 'credit_limit', 'opening_balance', 'notes'];

    protected $casts = ['credit_limit' => 'float', 'opening_balance' => 'float', 'payment_terms_days' => 'integer'];
}
