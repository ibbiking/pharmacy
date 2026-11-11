<?php

namespace App\Models\Expense;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /** Default categories (name => account code in the default chart), seeded when Expense is switched on. */
    public const DEFAULTS = [
        'Rent' => '6020', 'Utilities' => '6030', 'Telephone & internet' => '6040', 'Office supplies' => '6070', 'Repairs & maintenance' => '6080',
        'Travel' => '6100', 'Meals & entertainment' => '6110', 'Fuel & transport' => '6120', 'Professional fees' => '6130', 'Advertising' => '6000',
        'Printing & stationery' => '6160', 'Courier & postage' => '6170', 'Software & subscriptions' => '6180', 'Bank charges' => '6010', 'Other' => '6240',
    ];

    protected $fillable = ['name', 'account_code', 'description'];

    public function expenses() { return $this->hasMany(Expense::class); }
}
