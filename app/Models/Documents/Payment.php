<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Model;

/** Money received from a customer (direction in) or paid to a supplier (out), Roadmap 8–9. */
class Payment extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const METHODS = ['cash' => 'Cash', 'bank' => 'Bank transfer', 'card' => 'Card', 'cheque' => 'Cheque', 'other' => 'Other'];

    protected $fillable = ['direction', 'number', 'customer_id', 'supplier_id', 'date', 'amount', 'unapplied', 'method', 'reference', 'notes', 'created_by'];

    protected $casts = ['date' => 'date', 'amount' => 'float', 'unapplied' => 'float'];

    public function allocations() { return $this->hasMany(PaymentAllocation::class); }
    public function customer() { return $this->belongsTo(\App\Models\Customer::class)->withTrashed(); }
    public function supplier() { return $this->belongsTo(\App\Models\Supplier::class)->withTrashed(); }
    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
