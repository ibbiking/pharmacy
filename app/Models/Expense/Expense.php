<?php

namespace App\Models\Expense;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'number', 'date', 'expense_category_id', 'merchant', 'description', 'amount', 'tax_id', 'tax_amount', 'paid_by', 'payment_method', 'receipt_path',
        'status', 'user_id', 'approved_by', 'approved_at', 'rejection_reason', 'reimbursed_by', 'reimbursed_at',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'float', 'tax_amount' => 'float', 'approved_at' => 'datetime', 'reimbursed_at' => 'datetime'];

    public function category() { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed(); }
    public function user() { return $this->belongsTo(\App\Models\User::class); }
    public function approver() { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }
    public function tax() { return $this->belongsTo(\App\Models\Tax::class)->withTrashed(); }
}
