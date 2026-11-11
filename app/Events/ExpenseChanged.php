<?php

namespace App\Events;

use App\Models\Expense\Expense;
use Illuminate\Foundation\Events\Dispatchable;

/** An expense was approved, reimbursed, rejected or archived. */
class ExpenseChanged
{
    use Dispatchable;

    public function __construct(public Expense $expense)
    {
    }
}
