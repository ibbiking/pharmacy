<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Default chart of accounts for one business (Roadmap step 10), modelled on SeeBiz Books' 67 default accounts.
 * Idempotent: an account is matched by its system key (or code when it has none) and never duplicated.
 * Run per business: (new DefaultChartOfAccountsSeeder)->forBusiness($id); it runs when Accounting is switched on.
 */
class DefaultChartOfAccountsSeeder extends Seeder
{
    /** [code, name, sub type, system key|null, description] */
    public const ACCOUNTS = [
        // Assets
        ['1000', 'Cash in Hand', 'cash', 'cash', 'Cash sales, cash payments and the till.'],
        ['1010', 'Petty Cash', 'cash', null, 'Small day-to-day spending.'],
        ['1020', 'Bank Account', 'bank', 'bank', 'Bank transfers, cards and cheques.'],
        ['1030', 'Undeposited Funds', 'cash', null, 'Money received but not yet banked.'],
        ['1100', 'Accounts Receivable', 'receivable', 'ar', 'What customers owe you on invoices.'],
        ['1150', 'Advances to Suppliers', 'current_asset', null, null],
        ['1200', 'Inventory Asset', 'inventory', 'inventory', 'Stock on hand at cost.'],
        ['1250', 'Input Tax (Recoverable)', 'current_asset', 'input_tax', 'Sales tax / GST paid on purchases and expenses.'],
        ['1300', 'Prepaid Expenses', 'current_asset', null, null],
        ['1350', 'Employee Advances', 'current_asset', null, null],
        ['1400', 'Security Deposits', 'current_asset', null, null],
        ['1500', 'Furniture and Fixtures', 'fixed_asset', null, null],
        ['1510', 'Computers and IT Equipment', 'fixed_asset', null, null],
        ['1520', 'Machinery and Equipment', 'fixed_asset', null, null],
        ['1530', 'Vehicles', 'fixed_asset', null, null],
        ['1540', 'Buildings and Improvements', 'fixed_asset', null, null],
        ['1590', 'Accumulated Depreciation', 'fixed_asset', null, null],
        // Liabilities
        ['2000', 'Accounts Payable', 'payable', 'ap', 'What you owe suppliers on bills.'],
        ['2050', 'Goods Received Not Billed', 'current_liability', 'grni', 'Stock received against a purchase order before its bill.'],
        ['2100', 'Sales Tax Payable', 'tax', 'tax_payable', 'Sales tax / GST collected and owed to the tax authority.'],
        ['2110', 'Withholding Tax Payable', 'tax', null, null],
        ['2150', 'Employee Reimbursements Payable', 'current_liability', 'employee_payable', 'Expenses employees paid and you owe them.'],
        ['2200', 'Customer Advances', 'current_liability', null, null],
        ['2300', 'Accrued Expenses', 'current_liability', null, null],
        ['2400', 'Salaries Payable', 'current_liability', null, null],
        ['2500', 'Credit Card Payable', 'current_liability', null, null],
        ['2600', 'Short-term Loans', 'current_liability', null, null],
        ['2700', 'Long-term Loans', 'long_term_liability', null, null],
        // Equity
        ['3000', 'Owner’s Capital', 'equity', null, null],
        ['3100', 'Owner’s Drawings', 'equity', null, null],
        ['3200', 'Opening Balance Equity', 'equity', 'opening_equity', 'The other side of opening balances.'],
        ['3300', 'Retained Earnings', 'equity', 'retained_earnings', 'Profit kept in the business.'],
        // Income
        ['4000', 'Sales', 'income', 'sales', 'POS and B2B sales, after discounts, before tax.'],
        ['4050', 'Sales Returns', 'income', 'sales_returns', 'Returns and credit notes (reduces sales).'],
        ['4100', 'Service Income', 'income', null, null],
        ['4200', 'Delivery Charges Income', 'income', null, null],
        ['4400', 'Other Income', 'other_income', 'other_income', null],
        ['4450', 'Interest Income', 'other_income', null, null],
        ['4500', 'Discounts Received', 'other_income', null, null],
        // Cost of sales
        ['5000', 'Cost of Goods Sold', 'cost_of_sales', 'cogs', 'The cost of the batches sold.'],
        ['5050', 'Purchase Price Variance', 'cost_of_sales', 'price_variance', 'Difference between the received value and the bill.'],
        ['5100', 'Inventory Adjustments', 'cost_of_sales', 'inventory_adjustment', 'Damaged, expired, lost or counted stock.'],
        ['5200', 'Freight and Carriage In', 'cost_of_sales', null, null],
        // Expenses
        ['6000', 'Advertising and Marketing', 'expense', null, null],
        ['6010', 'Bank Fees and Charges', 'expense', null, null],
        ['6020', 'Rent', 'expense', null, null],
        ['6030', 'Utilities (Electricity, Gas, Water)', 'expense', null, null],
        ['6040', 'Telephone and Internet', 'expense', null, null],
        ['6050', 'Salaries and Wages', 'expense', null, null],
        ['6060', 'Employee Benefits', 'expense', null, null],
        ['6070', 'Office Supplies', 'expense', null, null],
        ['6080', 'Repairs and Maintenance', 'expense', null, null],
        ['6090', 'Insurance', 'expense', null, null],
        ['6100', 'Travel', 'expense', null, null],
        ['6110', 'Meals and Entertainment', 'expense', null, null],
        ['6120', 'Fuel and Transport', 'expense', null, null],
        ['6130', 'Professional Fees', 'expense', null, null],
        ['6140', 'Licenses and Permits', 'expense', null, null],
        ['6150', 'Depreciation', 'expense', null, null],
        ['6160', 'Printing and Stationery', 'expense', null, null],
        ['6170', 'Postage and Courier', 'expense', null, null],
        ['6180', 'Software and Subscriptions', 'expense', null, null],
        ['6190', 'Training and Education', 'expense', null, null],
        ['6200', 'Cleaning and Sanitation', 'expense', null, null],
        ['6230', 'Bad Debts', 'expense', null, null],
        ['6240', 'Miscellaneous Expenses', 'expense', 'misc_expense', null],
        ['7000', 'Income Tax Expense', 'other_expense', null, null],
        ['7100', 'Interest Expense', 'other_expense', null, null],
    ];

    public function run(): void
    {
        foreach (DB::table('business_modules')->where('module', 'accounting')->where('status', 'active')->whereNull('deleted_at')->pluck('business_id') as $id) {
            $this->forBusiness((int) $id);
        }
    }

    public function forBusiness(int $businessId): void
    {
        $now = now();
        foreach (self::ACCOUNTS as [$code, $name, $sub, $key, $description]) {
            $exists = DB::table('accounts')->where('business_id', $businessId)->whereNull('deleted_at')
                ->when($key, fn ($q) => $q->where('system_key', $key), fn ($q) => $q->where('code', $code))->exists();
            if (! $exists) {
                DB::table('accounts')->insert([
                    'business_id' => $businessId, 'code' => $code, 'name' => $name, 'type' => \App\Models\Accounting\Account::SUB_TYPES[$sub][0],
                    'sub_type' => $sub, 'system_key' => $key, 'description' => $description, 'is_system' => $key !== null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
}
