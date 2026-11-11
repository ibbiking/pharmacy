<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const TYPES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];

    /** sub type => [type, label] */
    public const SUB_TYPES = [
        'cash' => ['asset', 'Cash'], 'bank' => ['asset', 'Bank'], 'receivable' => ['asset', 'Accounts receivable'], 'inventory' => ['asset', 'Inventory'],
        'current_asset' => ['asset', 'Other current asset'], 'fixed_asset' => ['asset', 'Fixed asset'],
        'payable' => ['liability', 'Accounts payable'], 'tax' => ['liability', 'Tax'], 'current_liability' => ['liability', 'Other current liability'],
        'long_term_liability' => ['liability', 'Long-term liability'], 'equity' => ['equity', 'Equity'],
        'income' => ['income', 'Income'], 'other_income' => ['income', 'Other income'],
        'cost_of_sales' => ['expense', 'Cost of sales'], 'expense' => ['expense', 'Expense'], 'other_expense' => ['expense', 'Other expense'],
    ];

    protected $fillable = ['code', 'name', 'type', 'sub_type', 'system_key', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function lines() { return $this->hasMany(JournalLine::class); }

    /** Debit-normal accounts grow with debits (assets, expenses). */
    public function debitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }
}
