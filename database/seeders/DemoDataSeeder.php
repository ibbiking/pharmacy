<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency\GlobalCurrency;
use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Expense\ExpenseCategory;
use App\Models\Inventory\Warehouse;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductParameter;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\LedgerPoster;
use App\Services\Documents\DocumentService;
use App\Services\Expense\ExpenseService;
use App\Services\Modules\ModuleManager;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\ReturnService;
use App\Services\Sales\SalePreferenceResolver;
use App\Services\Sales\SalePriceCalculator;
use App\Services\Stock\PurchaseService;
use App\Services\Stock\StockMovementService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Demo data (docs/DEMO_ACCOUNTS.md): one owner with three businesses — Pharmacy, Retail & Inventory and Wholesale &
 * Distribution — each with EVERY module its type may have (config/modules.php → types.*.allowed), and data that went
 * through every flow using the real services: purchases, warehouses, transfers, adjustments, POS sales and a return,
 * expenses with approvals; the Wholesale business also has quotations → orders → invoices → payments → credit notes
 * and purchase orders → receipts → bills → payments → vendor credits. The books are posted as it goes.
 *
 * Idempotent: a business that already exists for the demo owner is left as it is. Never for production databases:
 *   DB_DATABASE=pharmacy_qa php artisan db:seed --class=DemoDataSeeder --force
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass!2026';
    public const OWNER = 'demo.owner@example.test';
    public const ACCOUNTANT = 'demo.accountant@example.test';
    public const CASHIER = 'demo.cashier@example.test';

    private User $owner;
    private User $accountant;
    private User $cashier;
    private Business $business;
    private array $units = [];
    private ?Carbon $realToday = null;
    private array $products = [];

    public function run(): void
    {
        Carbon::setTestNow();
        $this->realToday = Carbon::today();
        if (app()->environment('production')) {
            $this->command?->error('Demo data is never seeded into production.');

            return;
        }
        Role::findOrCreate('Business Owner');
        Role::findOrCreate('sales-person');
        $this->owner = $this->user(self::OWNER, 'Demo Owner');
        $this->owner->assignRole('Business Owner');
        $this->accountant = $this->user(self::ACCOUNTANT, 'Demo Accountant');
        $this->cashier = $this->user(self::CASHIER, 'Demo Cashier');
        $this->cashier->assignRole('sales-person');

        foreach (self::BUSINESSES as $name => $spec) {
            $existing = $this->owner->businesses()->where('name', $name)->first();
            if ($existing) {
                $this->command?->line("“{$name}” exists already: left as it is.");
                continue;
            }
            $this->seedBusiness($name, $spec);
            $this->command?->info("“{$name}” seeded.");
        }
        Carbon::setTestNow();
        Auth::logout();
    }

    private const BUSINESSES = [
        'Demo Pharmacy' => ['type' => 'pharmacy', 'company' => 'GSK', 'product_type' => 'Tablet', 'items' => [
            // name, chain [unit => per previous], cost per top unit, sale per top unit, expiry months, taxed
            ['Panadol 500mg', ['Box' => 1, 'Strip' => 10, 'Tablet' => 10], 450, 600, 18, false],
            ['Augmentin 625mg', ['Box' => 1, 'Tablet' => 14], 780, 980, 14, false],
            ['Brufen Syrup 120ml', ['Carton' => 1, 'Bottle' => 12], 1440, 1920, 20, true],
            ['ORS Sachet', ['Box' => 1, 'Sachet' => 20], 300, 440, 24, false],
            ['Calpol 250mg Syrup', ['Carton' => 1, 'Bottle' => 24], 2160, 2880, 16, true],
        ]],
        'Demo Inventory Store' => ['type' => 'inventory', 'company' => 'Generic Brand', 'product_type' => 'Stationery', 'items' => [
            ['A4 Paper Ream', ['Carton' => 1, 'Ream' => 5], 6000, 7750, null, true],
            ['Ballpoint Pen Blue', ['Box' => 1, 'Piece' => 50], 1000, 1750, null, false],
            ['LED Bulb 12W', ['Carton' => 1, 'Piece' => 20], 6400, 9000, null, true],
            ['Spiral Notebook', ['Pack' => 1, 'Piece' => 10], 1200, 1800, null, false],
            ['Stapler Medium', ['Box' => 1, 'Piece' => 12], 3600, 5400, null, false],
        ]],
        'Demo B2B Distributors' => ['type' => 'inventory_b2b', 'company' => 'National Foods', 'product_type' => 'Grocery', 'items' => [
            ['Basmati Rice 5kg', ['Carton' => 1, 'Bag' => 4], 7200, 8800, 12, false],
            ['Cooking Oil 1L', ['Carton' => 1, 'Bottle' => 12], 6000, 7200, 9, true],
            ['Sugar 1kg', ['Sack' => 1, 'Pack' => 50], 7000, 8250, 12, false],
            ['Tea 950g', ['Carton' => 1, 'Pack' => 12], 12600, 15000, 18, true],
            ['Washing Powder 1kg', ['Carton' => 1, 'Pack' => 10], 3500, 4500, 24, true],
        ]],
    ];

    private function user(string $email, string $name): User
    {
        $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(self::PASSWORD)]);
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    /** Move the clock to $daysAgo (09:00 + $hour) so every record, journal and chart has a real date. */
    private function at(int $daysAgo, int $hour = 0): void
    {
        // Always from the real today (a moved clock would otherwise move the next step too).
        $this->realToday ??= Carbon::today();
        Carbon::setTestNow($this->realToday->copy()->subDays($daysAgo)->setTime(9 + $hour, 15));
    }

    private function as(User $user): void
    {
        Auth::setUser($user);
        app(\App\Services\Access\PermissionService::class)->forget();
    }

    private function seedBusiness(string $name, array $spec): void
    {
        $this->at(46);
        $pkr = GlobalCurrency::query()->whereNull('business_id')->where('currency_code', 'PKR')->value('id');
        $this->business = Business::create(['name' => $name, 'business_type' => $spec['type'], 'currency_id' => $pkr, 'created_by' => $this->owner->id,
            'phone' => '+92 300 0000000', 'address' => 'Demo address, Lahore']);
        $b = $this->business;
        $this->owner->businesses()->attach($b->id, ['role' => 'owner']);
        session(['business_id' => $b->id]);
        $this->as($this->owner);

        // Every module this type can buy (dependencies come with them). Accounting first, so everything posts live.
        $modules = app(ModuleManager::class);
        $modules->activate($b, 'accounting', 'free', $this->owner->id);
        foreach ($modules->allowedFor($spec['type']) as $m) {
            if ($m !== 'core' && ($modules->definition($m)['available'] ?? true)) {
                $modules->activate($b, $m, 'free', $this->owner->id);
            }
        }
        $modules->forget();
        app(\App\Services\Stock\WarehouseService::class)->ensureDefault($b->id);
        app(AccountingService::class)->ensureChart($b->id);
        app(ExpenseService::class)->ensureDefaults($b->id);

        // Team: an accountant (books, approvals, payments) and a cashier (POS only).
        $accountantRole = BusinessRole::create(['business_id' => $b->id, 'name' => 'Accountant', 'description' => 'Books, approvals, payments.']);
        $accountantRole->syncPermissions(['core.dashboard.view', 'core.catalogue.view', 'core.customers.view', 'stock.purchases.view', 'stock.reports.view', 'stock.cost.view',
            'pos.invoices.view', 'pos.reports.view', 'pos.profit.view', 'inventory_pro.view', 'b2b_sales.view', 'b2b_sales.payments', 'b2b_purchase.view', 'b2b_purchase.payments',
            'accounting.view', 'accounting.manage', 'accounting.reports.view', 'expense.submit', 'expense.approve', 'expense.reimburse', 'expense.manage', 'analytics.view']);
        $this->accountant->businesses()->attach($b->id, ['role' => 'staff', 'business_role_id' => $accountantRole->id]);
        $cashierRole = BusinessRole::where('business_id', $b->id)->where('system_key', 'sales-person')->first();
        $this->cashier->businesses()->attach($b->id, ['role' => 'sales-person', 'business_role_id' => $cashierRole?->id]);

        // Master data.
        $gst = Tax::create(['name' => 'GST', 'rate' => 18]);
        $suppliers = collect([['Metro Wholesale', 'orders@metro.example', 30, 0], ['City Traders', 'sales@citytraders.example', 15, 25000]])
            ->map(fn ($s) => Supplier::create(['name' => $s[0], 'email' => $s[1], 'phone' => '+92 42 111 000 '.rand(100, 999), 'company' => $s[0].' (Pvt) Ltd',
                'address' => 'Hall Road, Lahore', 'payment_terms_days' => $s[2], 'opening_balance' => $s[3]]));
        $customers = collect([['Shifa Clinic', 30, 500000, 15000], ['Green Valley School', 15, 200000, 0], ['Al-Noor Hospital', 45, 1000000, 0]])
            ->map(fn ($c) => Customer::create(['name' => $c[0], 'company' => $c[0], 'email' => strtolower(str_replace(' ', '', $c[0])).'@example.test',
                'phone' => '+92 321 '.rand(1000000, 9999999), 'address' => 'Gulberg, Lahore', 'payment_terms_days' => $c[1], 'credit_limit' => $c[2], 'opening_balance' => $c[3]]));
        $main = Warehouse::where('is_default', true)->firstOrFail();
        $branch = Warehouse::create(['name' => 'Branch store', 'code' => 'BR-1', 'address' => 'Model Town, Lahore']);

        // Opening bank balance (manual journal).
        $books = app(AccountingService::class);
        $books->manual($b->id, ['date' => today()->toDateString(), 'memo' => 'Opening bank balance', 'lines' => [
            ['account_id' => $books->accountId($b->id, 'bank'), 'debit' => 500000], ['account_id' => $books->accountId($b->id, 'opening_equity'), 'credit' => 500000],
        ]], $this->owner->id);

        // Items with their packaging (largest unit first) and static prices.
        $company = Company::create(['name' => $spec['company']]);
        $type = ProductType::create(['name' => $spec['product_type']]);
        $this->products = [];
        foreach ($spec['items'] as [$itemName, $chain, $cost, $sale, $months, $taxed]) {
            $this->products[] = $this->product($itemName, $chain, $company->id, $type->id, $cost, $sale) + ['cost' => $cost, 'sale' => $sale, 'months' => $months, 'taxed' => $taxed];
        }

        // 1. Stock in: a plain purchase for every item into the main warehouse (45 days ago).
        $this->at(45);
        app(PurchaseService::class)->create(['supplier' => ['id' => $suppliers[0]->id], 'invoice_no' => 'MW-'.rand(1000, 9999), 'warehouse_id' => $main->id,
            'lines' => array_map(fn ($p) => $this->purchaseLine($p, 20, $gst), $this->products)]);
        $this->at(40);
        // Bought on credit (Supplier credit): its bill is kept; 40 % of it is paid a week later.
        $onCredit = app(PurchaseService::class)->create(['supplier' => ['id' => $suppliers[1]->id], 'invoice_no' => 'CT-'.rand(1000, 9999), 'warehouse_id' => $main->id,
            'payment' => 'unpaid', 'lines' => array_map(fn ($p) => $this->purchaseLine($p, 10, $gst, 1.02), array_slice($this->products, 0, 3))]);
        $this->at(33);
        $bill = app(\App\Services\Credit\CreditService::class)->linkedBill($onCredit->first());
        app(DocumentService::class)->recordPayment('out', ['supplier_id' => $suppliers[1]->id, 'date' => today()->toDateString(), 'amount' => round($bill->total * 0.4, 2),
            'method' => 'bank', 'reference' => 'Part payment', 'allocations' => [['document_id' => $bill->id, 'amount' => round($bill->total * 0.4, 2)]]], $this->owner);

        // 2. Warehouses: move stock to the branch; write off a damaged unit; the POS sells from the branch.
        $this->at(38);
        $moves = app(StockMovementService::class);
        $moves->transfer(['from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id, 'date' => today()->toDateString(), 'note' => 'Stock for the branch counter',
            'lines' => array_map(fn ($p) => ['product_id' => $p['product']->id, 'category_id' => $p['top'], 'quantity' => 6], $this->products)], $this->owner->id);
        $this->at(20);
        $moves->adjust(['warehouse_id' => $main->id, 'date' => today()->toDateString(), 'direction' => 'decrease', 'reason' => 'damaged', 'note' => 'Broken in handling',
            'lines' => [['product_id' => $this->products[2]['product']->id, 'category_id' => $this->products[2]['base'], 'quantity' => 2]]], $this->owner->id);
        app(\App\Services\Stock\WarehouseService::class)->setPosWarehouse($b->id, $branch->id);

        // 3. POS: sales by the cashier over the last month, one return.
        $checkout = app(CheckoutService::class);
        $invoices = [];
        foreach ([33, 27, 21, 15, 10, 6, 3, 1, 0] as $n => $daysAgo) {
            $this->at($daysAgo, $n % 6);
            $this->as($this->cashier);
            $lines = [];
            foreach ([$n % 5, ($n + 2) % 5] as $k => $i) {
                $p = $this->products[$i];
                $lines[] = ['product_id' => $p['product']->id, 'category_id' => $p['base'], 'qty' => 1 + ($n + $k) % 3, 'name' => $p['product']->product_name, 'price' => $this->price($p['product'], $p['base'])];
            }
            $input = ['invoice_discount_type' => 'amount', 'invoice_discount_value' => 0, 'cash_received' => 1000000];
            $seller = $this->cashier;
            if ($spec['type'] === 'inventory_b2b') {
                // Wholesale: every invoice is for a saved customer (no walk-in).
                $input['customer_id'] = $customers[$n % 3]->id;
            } elseif (in_array($n, [2, 5], true)) {
                // Credit for saved customers (owner sells: the built-in cashier role can't give credit).
                $input = ['customer_id' => $customers[$n === 2 ? 0 : 1]->id, 'on_credit' => true, 'cash_received' => $n === 2 ? 100 : 0] + $input;
                $seller = $this->owner;
                $this->as($seller);
            }
            $invoices[] = $checkout->checkout($lines, $input, $seller->id, $b->id)->invoice;
        }
        $this->as($this->owner);
        $this->at(14);
        $first = Invoice::with('items')->find($invoices[1]->id);
        app(ReturnService::class)->return($first, [$first->items->first()->id => 1], 'Customer changed their mind', $this->owner->id);

        // 4–5. B2B documents exist only in a Wholesale & Distribution business (one business = one type).
        if ($spec['type'] === 'inventory_b2b') {
            $this->seedB2bDocuments($b, $main, $suppliers, $customers, $gst);
        }
        $this->seedExpensesAndSettings($name, $b, $gst);
    }

    private function seedB2bDocuments(Business $b, $main, $suppliers, $customers, Tax $gst): void
    {
        // 4. B2B purchasing: PO → part receipt → bill → payment; second receipt; vendor credit.
        $docs = app(DocumentService::class);
        $this->at(30);
        $po = $docs->save('purchase_order', ['supplier_id' => $suppliers[0]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(), 'reference' => 'Monthly order',
            'lines' => array_map(fn ($p) => ['product_id' => $p['product']->id, 'category_id' => $p['top'], 'quantity' => 10, 'unit_price' => $p['cost'], 'tax_id' => $p['taxed'] ? $gst->id : null],
                array_slice($this->products, 0, 4))], null, $this->owner);
        $docs->action($po, 'send', $this->owner);
        $this->at(27);
        $docs->receive($po->fresh('lines'), ['date' => today()->toDateString(), 'warehouse_id' => $main->id, 'lines' => $po->lines->map(fn ($l, $i) => [
            'line_id' => $l->id, 'quantity' => $i < 3 ? 10 : 6, 'unit_sale_price' => $this->products[$i]['sale'], 'batch_no' => 'PO-'.($i + 1),
            'expiry_date' => $this->products[$i]['months'] ? today()->addMonths($this->products[$i]['months'])->toDateString() : null])->all()], $this->owner);
        $this->at(26);
        $bill = $docs->convert($po->fresh('lines'), 'bill', $this->owner);
        $docs->action($bill, 'issue', $this->owner);
        $this->at(12);
        $docs->recordPayment('out', ['supplier_id' => $suppliers[0]->id, 'date' => today()->toDateString(), 'amount' => round($bill->fresh()->total * 0.6, 2), 'method' => 'bank',
            'reference' => 'IBFT 55821', 'allocations' => [['document_id' => $bill->id, 'amount' => round($bill->fresh()->total * 0.6, 2)]]], $this->owner);
        $this->at(9);
        $vc = $docs->convert($bill->fresh('lines'), 'vendor_credit', $this->owner);
        $vcLine = $vc->lines->first();
        $docs->save('vendor_credit', ['supplier_id' => $suppliers[0]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(), 'source_id' => $bill->id, 'notes' => 'Short-dated stock returned',
            'lines' => [['source_line_id' => $vcLine->source_line_id, 'product_id' => $vcLine->product_id, 'category_id' => $vcLine->category_id, 'quantity' => 1]]], $vc, $this->owner);
        $docs->action($vc, 'issue', $this->owner);
        $this->at(5);
        $po2 = $docs->save('purchase_order', ['supplier_id' => $suppliers[1]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->products[4]['product']->id, 'category_id' => $this->products[4]['top'], 'quantity' => 8, 'unit_price' => $this->products[4]['cost']]]], null, $this->owner);
        $docs->action($po2, 'send', $this->owner); // waiting for delivery

        // 5. B2B sales: quotation → order → invoice → payment; an overdue invoice; a credit note; open quotations.
        $this->at(35);
        $quote = $docs->save('quotation', ['customer_id' => $customers[0]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(), 'terms' => 'Prices valid for 15 days.',
            'lines' => [['product_id' => $this->products[0]['product']->id, 'category_id' => $this->products[0]['top'], 'quantity' => 4, 'discount_percent' => 5],
                ['product_id' => $this->products[1]['product']->id, 'category_id' => $this->products[1]['top'], 'quantity' => 3, 'tax_id' => $gst->id]]], null, $this->owner);
        $docs->action($quote, 'send', $this->owner);
        $docs->action($quote, 'accept', $this->owner);
        $this->at(34);
        $order = $docs->convert($quote->fresh('lines'), 'sales_order', $this->owner);
        $docs->action($order, 'confirm', $this->owner);
        $this->at(33);
        $inv1 = $docs->convert($order->fresh('lines'), 'sales_invoice', $this->owner);
        $docs->action($inv1, 'issue', $this->owner); // 30-day terms: overdue now, partly paid
        // Shipping: two cartons with the courier, delivered the next day (delivery note printable).
        $shipping = app(\App\Services\Documents\ShipmentService::class);
        $shipment = $shipping->create($inv1->fresh(), ['carrier' => 'TCS Courier', 'tracking_no' => 'TCS-'.rand(100000, 999999), 'packages' => 2, 'weight_kg' => 24.5, 'shipped' => true], $this->owner);
        $this->at(32);
        $shipping->advance($shipment, 'delivered');
        $this->at(33);
        $this->at(16);
        $docs->recordPayment('in', ['customer_id' => $customers[0]->id, 'date' => today()->toDateString(), 'amount' => round($inv1->fresh()->total / 2, 2), 'method' => 'cheque',
            'reference' => 'CHQ 004512', 'allocations' => [['document_id' => $inv1->id, 'amount' => round($inv1->fresh()->total / 2, 2)]]], $this->owner);

        $this->at(18);
        $inv2 = $docs->save('sales_invoice', ['customer_id' => $customers[1]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->products[3]['product']->id, 'category_id' => $this->products[3]['top'], 'quantity' => 3],
                ['product_id' => $this->products[4]['product']->id, 'category_id' => $this->products[4]['top'], 'quantity' => 2, 'tax_id' => $gst->id]]], null, $this->owner);
        $docs->action($inv2, 'issue', $this->owner);
        $this->at(8);
        $docs->recordPayment('in', ['customer_id' => $customers[1]->id, 'date' => today()->toDateString(), 'amount' => $inv2->fresh()->total, 'method' => 'bank',
            'allocations' => [['document_id' => $inv2->id, 'amount' => $inv2->fresh()->total]]], $this->owner);
        $this->at(7);
        $cn = $docs->convert($inv2->fresh('lines'), 'credit_note', $this->owner);
        $cnLine = $cn->lines->first();
        $docs->save('credit_note', ['customer_id' => $customers[1]->id, 'date' => today()->toDateString(), 'source_id' => $inv2->id, 'notes' => 'One pack returned',
            'lines' => [['source_line_id' => $cnLine->source_line_id, 'product_id' => $cnLine->product_id, 'category_id' => $cnLine->category_id, 'quantity' => 1]]], $cn, $this->owner);
        $docs->action($cn, 'issue', $this->owner); // invoice was paid: stays as the customer's credit

        $this->at(4);
        $inv3 = $docs->save('sales_invoice', ['customer_id' => $customers[2]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(), 'reference' => 'AN-PO-7781',
            'lines' => [['product_id' => $this->products[0]['product']->id, 'category_id' => $this->products[0]['top'], 'quantity' => 5],
                ['product_id' => $this->products[2]['product']->id, 'category_id' => $this->products[2]['top'], 'quantity' => 1, 'tax_id' => $gst->id]]], null, $this->owner);
        $docs->action($inv3, 'issue', $this->owner); // open, not due yet
        $this->at(2);
        $q2 = $docs->save('quotation', ['customer_id' => $customers[2]->id, 'warehouse_id' => $main->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->products[1]['product']->id, 'category_id' => $this->products[1]['top'], 'quantity' => 10, 'discount_percent' => 7.5]]], null, $this->owner);
        $docs->action($q2, 'send', $this->owner);
        $this->at(11);
        $q3 = $docs->save('quotation', ['customer_id' => $customers[1]->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->products[3]['product']->id, 'category_id' => $this->products[3]['top'], 'quantity' => 20]]], null, $this->owner);
        $docs->action($q3, 'decline', $this->owner);
    }

    private function seedExpensesAndSettings(string $name, Business $b, Tax $gst): void
    {
        // 6. Expenses: rent paid by the business (approved by the accountant); fuel paid by the accountant (approved and
        // reimbursed by the owner); one waiting for approval; one rejected.
        $exp = app(ExpenseService::class);
        $cat = fn (string $n) => ExpenseCategory::where('name', $n)->value('id');
        $this->at(30);
        $rent = $exp->save(['date' => today()->toDateString(), 'expense_category_id' => $cat('Rent'), 'merchant' => 'Property owner', 'description' => 'Shop rent', 'amount' => 85000,
            'paid_by' => 'business', 'payment_method' => 'bank', 'submit' => true], null, null, $this->owner, $b->id);
        $exp->transition($rent, 'approve', $this->accountant, $b->id);
        $this->at(17);
        $fuel = $exp->save(['date' => today()->toDateString(), 'expense_category_id' => $cat('Fuel & transport'), 'merchant' => 'PSO Gulberg', 'description' => 'Delivery van fuel',
            'amount' => 12500, 'tax_id' => $gst->id, 'paid_by' => 'employee', 'submit' => true], null, null, $this->accountant, $b->id);
        $exp->transition($fuel, 'approve', $this->owner, $b->id);
        $this->at(15);
        $exp->transition($fuel, 'reimburse', $this->owner, $b->id);
        $this->at(6);
        $exp->save(['date' => today()->toDateString(), 'expense_category_id' => $cat('Utilities'), 'merchant' => 'LESCO', 'description' => 'Electricity bill', 'amount' => 23800,
            'paid_by' => 'business', 'payment_method' => 'cash', 'submit' => true], null, null, $this->accountant, $b->id);
        $this->at(3);
        $snacks = $exp->save(['date' => today()->toDateString(), 'expense_category_id' => $cat('Meals & entertainment'), 'merchant' => 'Cafe', 'amount' => 4200,
            'paid_by' => 'employee', 'submit' => true], null, null, $this->accountant, $b->id);
        $exp->transition($snacks, 'reject', $this->owner, $b->id, 'Please attach the receipt.');

        // Its own email settings (docs: each business has its own SMTP). The SMTP account itself (host, user, password)
        // is entered by the owner on Settings → Email & branding; until then the platform mailer sends under this name.
        $sender = ['Demo Pharmacy' => 'pharmacy', 'Demo Inventory Store' => 'store', 'Demo B2B Distributors' => 'sales'][$name] ?? 'hello';
        $mail = \App\Models\BusinessMailSetting::firstOrNew(['business_id' => $b->id]);
        $mail->fill(['driver' => 'platform', 'from_name' => $name, 'from_address' => "{$sender}@demo.example.test", 'reply_to' => "{$sender}@demo.example.test"])->save();

        // Analytics weekly email on; the books include everything (backfill is idempotent).
        \Illuminate\Support\Facades\DB::table('preferences')->insert(['business_id' => $b->id, 'type' => 'analytics', 'slug' => 'weekly-summary', 'preference' => 'Weekly summary email to owners',
            'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        Carbon::setTestNow();
        app(LedgerPoster::class)->backfill($b->id);
    }

    /** @return array{product: Product, top: int, base: int} */
    private function product(string $name, array $chain, int $companyId, int $typeId, float $cost, float $sale): array
    {
        $product = Product::create(['product_name' => $name, 'company_id' => $companyId, 'product_type_id' => $typeId, 'is_draft' => false,
            'barcode' => (string) random_int(100000000000, 999999999999)]);
        $ids = [];
        foreach (array_keys($chain) as $unit) {
            $ids[$unit] = Category::firstOrCreate(['name' => $unit])->id;
        }
        $units = array_keys($chain);
        $root = $ids[$units[0]];
        $perTop = 1.0;
        foreach ($units as $i => $u) {
            $perTop *= $i === 0 ? 1 : (float) $chain[$u];
            ProductParameter::create(['product_id' => $product->id, 'category_id' => $root, 'parent_category_id' => $i === 0 ? $root : $ids[$units[$i - 1]],
                'child_category_id' => $ids[$u], 'quantity' => $i === 0 ? 1 : $chain[$u],
                'static_category_unit_sale_price' => round($sale / $perTop, 2), 'static_category_unit_purchase_price' => round($cost / $perTop, 2)]);
        }

        return ['product' => $product->fresh(), 'top' => $root, 'base' => $ids[end($units)]];
    }

    private function purchaseLine(array $p, float $qty, Tax $gst, float $costFactor = 1.0): array
    {
        return [
            'product_id' => $p['product']->id, 'category_id' => $p['top'], 'quantity' => $qty, 'unit_cost_price' => round($p['cost'] * $costFactor, 2),
            'unit_sale_price' => $p['sale'], 'batch_no' => 'B'.today()->format('md').'-'.$p['product']->id,
            'expiry_date' => $p['months'] ? today()->addMonths($p['months'])->toDateString() : null,
            'sale_tax_ids' => $p['taxed'] ? [$gst->id] : [],
        ];
    }

    private function price(Product $product, int $categoryId): float
    {
        return round(app(SalePriceCalculator::class)->unitPrice($product->id, $categoryId, app(SalePreferenceResolver::class)->forProduct($product)), 2);
    }
}
