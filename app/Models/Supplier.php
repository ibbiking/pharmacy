<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'name','email','phone','company',
        'address','product','comment', 'tax_number', 'payment_terms_days', 'opening_balance',
    ];

    protected $casts = ['payment_terms_days' => 'integer', 'opening_balance' => 'float'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
