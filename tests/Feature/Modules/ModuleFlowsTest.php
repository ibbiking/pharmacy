<?php

namespace Tests\Feature\Modules;

use App\Models\Accounting\JournalLine;
use App\Models\BaseStockSalePrice;
use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Inventory\Warehouse;
use App\Models\Purchase;
use App\Services\Accounting\AccountingService;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 7–12: every new module through its flow, end to end, with the books balancing at each step. */
class ModuleFlowsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario {
        saveInvoice as scenarioSaveInvoice;
    }

    /** A Wholesale invoice is always for a saved customer (no walk-in). */
    protected function saveInvoice(array $lines, array $invoice = []): \Illuminate\Testing\TestResponse
    {
        $walkIn = Customer::firstOrCreate(['name' => 'Counter customer']);

        return $this->scenarioSaveInvoice($lines, $invoice + ['customer_id' => $walkIn->id]);
    }

    private $product;
    private array $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // A Wholesale & Distribution business: the only type that may have every one of these modules.
        $this->setUpBusiness('Alpha', 'inventory_b2b');
        foreach (['inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense', 'analytics'] as $m) {
            app(ModuleManager::class)->activate($this->business, $m);
        }
        app(ModuleManager::class)->forget();
        [$this->product, $this->unit] = $this->makeProduct('Paracetamol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6], 'Box' => [100, 60]]);
        $this->purchase($this->product, $this->unit['Box'], 10, 60, 100, now()->addYear()->toDateString()); // 100 tablets at 6
    }

    private function assertBooksBalance(): void
    {
        $dr = round((float) JournalLine::sum('debit'), 2);
        $cr = round((float) JournalLine::sum('credit'), 2);
        $this->assertSame($dr, $cr, 'Debits and credits must be equal');
    }

    private function balance(string $key): float
    {
        $id = app(AccountingService::class)->accountId($this->business->id, $key);
        $l = JournalLine::where('account_id', $id);

        return round((float) $l->sum('debit') - (float) (clone $l)->sum('credit'), 2);
    }

    public function test_inventory_pro_warehouses_transfers_adjustments(): void
    {
        $main = Warehouse::where('is_default', true)->firstOrFail();
        $this->assertSame(0, BaseStockSalePrice::whereNull('warehouse_id')->count());
        $this->post(route('warehouses.store'), ['name' => 'Branch store'])->assertSessionHasNoErrors();
        $branch = Warehouse::where('name', 'Branch store')->firstOrFail();

        $this->post(route('transfers.store'), ['from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 3]]])->assertSessionHasNoErrors();
        $this->assertEquals(30, BaseStockSalePrice::where('warehouse_id', $branch->id)->sum('remaining_base_stock'));
        $this->assertEquals(70, BaseStockSalePrice::where('warehouse_id', $main->id)->sum('remaining_base_stock'));

        // A batch that was moved is fixed.
        $purchase = Purchase::firstOrFail();
        $this->assertNotNull(app(\App\Services\Stock\PurchaseService::class)->lockReason($purchase));

        // The POS sells only from its warehouse once set.
        $this->post(route('warehouses.pos'), ['warehouse_id' => $branch->id])->assertSessionHasNoErrors();
        app()->forgetScopedInstances();
        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 31, 10)])->assertStatus(400); // only 30 in the POS warehouse
        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 5, 10)])->assertOk();
        $this->assertEquals(25, BaseStockSalePrice::where('warehouse_id', $branch->id)->sum('remaining_base_stock'));

        $this->post(route('adjustments.store'), ['warehouse_id' => $main->id, 'date' => today()->toDateString(), 'direction' => 'decrease', 'reason' => 'damaged',
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Tablet'], 'quantity' => 4]]])->assertSessionHasNoErrors();
        $this->assertEquals(66, BaseStockSalePrice::where('warehouse_id', $main->id)->sum('remaining_base_stock'));
        $this->assertEquals(24.0, $this->balance('inventory_adjustment')); // 4 tablets × 6
        $this->assertBooksBalance();

        $this->get(route('inventory.valuation'))->assertOk();
        $this->get(route('warehouses.index'))->assertOk();
        $this->get(route('transfers.index'))->assertOk();
    }

    public function test_b2b_sales_quote_to_payment_and_credit_note_with_books(): void
    {
        $this->post(route('customers.store'), ['name' => 'City Clinic', 'payment_terms_days' => 30, 'opening_balance' => 50])->assertSessionHasNoErrors();
        $customer = Customer::firstOrFail();
        $this->assertSame(50.0, $this->balance('ar'));

        $this->post(route('selling.quotations.store'), ['customer_id' => $customer->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 2, 'discount_percent' => 10]]])->assertSessionHasNoErrors();
        $quote = Document::where('type', 'quotation')->firstOrFail();
        $this->assertSame(180.0, $quote->total); // 2 × 100 − 10 %

        $this->post(route('selling.docs.action', [$quote->id, 'convert:sales_invoice']))->assertSessionHasNoErrors();
        $invoice = Document::where('type', 'sales_invoice')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(today()->addDays(30)->toDateString(), $invoice->due_date->toDateString());
        $this->post(route('selling.docs.action', [$invoice->id, 'issue']))->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame(120.0, $invoice->cost_total); // 20 tablets × 6
        $this->assertEquals(80, BaseStockSalePrice::sum('remaining_base_stock'));
        $this->assertSame('converted', $quote->fresh()->status);

        $this->post(route('selling.payments.store'), ['customer_id' => $customer->id, 'date' => today()->toDateString(), 'amount' => 100, 'method' => 'bank',
            'allocations' => [['document_id' => $invoice->id, 'amount' => 100]]])->assertSessionHasNoErrors();
        $this->assertSame('partial', $invoice->fresh()->status);
        $this->assertSame(80.0, $invoice->fresh()->balance);

        // Credit note for one box: restocks, settles part of the invoice.
        $this->post(route('selling.docs.action', [$invoice->id, 'convert:credit_note']))->assertSessionHasNoErrors();
        $credit = Document::where('type', 'credit_note')->firstOrFail();
        $line = $credit->lines()->firstOrFail();
        $this->put(route('selling.docs.update', $credit->id), ['customer_id' => $customer->id, 'date' => today()->toDateString(),
            'lines' => [['source_line_id' => $line->source_line_id, 'product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 1]]])->assertSessionHasNoErrors();
        $this->post(route('selling.docs.action', [$credit->id, 'issue']))->assertSessionHasNoErrors();
        $this->assertEquals(90, BaseStockSalePrice::sum('remaining_base_stock'));
        $this->assertSame(0.0, $invoice->fresh()->balance); // 80 open − 90 credit → 0, 10 left as credit
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(10.0, $credit->fresh()->balance);

        // A/R: 50 opening + 180 invoice − 100 paid − 90 credit = 40; customer balance = 50 + 0 − 10 credit = 40.
        $this->assertSame(40.0, $this->balance('ar'));
        $this->assertEquals(40, Customer::query()->select('customers.*')->selectSub(\App\Http\Controllers\Admin\CustomerController::balanceSql(), 'b')->first()->b);
        $this->assertSame(-90.0, $this->balance('sales') + $this->balance('sales_returns')); // 180 sold − 90 credited (credit-normal → negative)
        $this->assertBooksBalance();

        $this->get(route('selling.invoices.index'))->assertOk();
        $this->get(route('selling.docs.show', $invoice->id))->assertOk();
        $this->get(route('selling.docs.print', $invoice->id))->assertOk()->assertSee($invoice->number);
        $this->get(route('customers.statement', $customer->id))->assertOk()->assertSee('City Clinic');
        $this->get(route('reports.ar_aging'))->assertOk();
    }

    public function test_b2b_purchasing_po_receive_bill_pay_and_vendor_credit(): void
    {
        $supplier = $this->supplier;
        $this->post(route('buying.orders.store'), ['supplier_id' => $supplier->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 5, 'unit_price' => 55]]])->assertSessionHasNoErrors();
        $po = Document::where('type', 'purchase_order')->firstOrFail();
        $line = $po->lines()->firstOrFail();
        $this->post(route('buying.docs.action', [$po->id, 'convert:bill']))->assertSessionHasErrors('document'); // nothing received yet

        $this->post(route('buying.docs.receive', $po->id), ['date' => today()->toDateString(),
            'lines' => [['line_id' => $line->id, 'quantity' => 3, 'unit_sale_price' => 100, 'expiry_date' => now()->addYear()->toDateString(), 'batch_no' => 'PO-B1']]])->assertSessionHasNoErrors();
        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertEquals(130, BaseStockSalePrice::sum('remaining_base_stock'));
        $this->assertSame(-165.0, $this->balance('grni')); // 3 × 55 received, not billed

        $this->post(route('buying.docs.action', [$po->id, 'convert:bill']))->assertSessionHasNoErrors();
        $bill = Document::where('type', 'bill')->firstOrFail();
        $this->assertEquals(3, $bill->lines()->first()->qty);
        $this->post(route('buying.docs.action', [$bill->id, 'issue']))->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->balance('grni'));
        $this->assertSame(-165.0, $this->balance('ap'));

        $this->post(route('buying.payments.store'), ['supplier_id' => $supplier->id, 'date' => today()->toDateString(), 'amount' => 165, 'method' => 'cash',
            'allocations' => [['document_id' => $bill->id, 'amount' => 165]]])->assertSessionHasNoErrors();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(0.0, $this->balance('ap'));

        $this->post(route('buying.docs.action', [$bill->id, 'convert:vendor_credit']))->assertSessionHasNoErrors();
        $vc = Document::where('type', 'vendor_credit')->firstOrFail();
        $vcLine = $vc->lines()->firstOrFail();
        $this->put(route('buying.docs.update', $vc->id), ['supplier_id' => $supplier->id, 'date' => today()->toDateString(),
            'lines' => [['source_line_id' => $vcLine->source_line_id, 'product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 1]]])->assertSessionHasNoErrors();
        $this->post(route('buying.docs.action', [$vc->id, 'issue']))->assertSessionHasNoErrors();
        $this->assertEquals(120, BaseStockSalePrice::sum('remaining_base_stock'));
        $this->assertSame(55.0, $vc->fresh()->balance); // bill was paid: kept as credit with the supplier
        $this->assertBooksBalance();

        $this->get(route('buying.orders.index'))->assertOk();
        $this->get(route('buying.docs.receive-form', $po->id))->assertOk();
        $this->get(route('suppliers.statement', $supplier->id))->assertOk();
        $this->get(route('reports.ap_aging'))->assertOk();
    }

    public function test_pos_sale_and_return_post_to_the_books_and_backfill_is_idempotent(): void
    {
        $invoiceNo = $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 10, 10)])->assertOk()->json('invoice_no')
            ?? \App\Models\Invoice::latest('id')->value('invoice_no');
        $this->assertSame(-500.0, $this->balance('cash')); // purchase paid 600 in cash, sale +100
        $this->assertSame(60.0, $this->balance('cogs'));
        $this->postJson(route('invoices.return', $invoiceNo), ['qty' => null])->assertOk();
        $this->assertSame(0.0, $this->balance('cogs'));
        $before = JournalLine::count();
        app(\App\Services\Accounting\LedgerPoster::class)->backfill($this->business->id);
        $this->assertSame($before, JournalLine::count());
        $this->assertBooksBalance();
        $tb = app(AccountingService::class)->trialBalance($this->business->id, today()->toDateString());
        $this->assertSame($tb['debit'], $tb['credit']);
    }

    public function test_expenses_approval_policy_reimbursement_and_books(): void
    {
        $category = \App\Models\Expense\ExpenseCategory::where('name', 'Rent')->firstOrFail(); // seeded with the module
        $this->post(route('expenses.store'), ['date' => today()->toDateString(), 'expense_category_id' => $category->id, 'merchant' => 'Landlord', 'amount' => 1000,
            'paid_by' => 'employee', 'submit' => 1])->assertSessionHasNoErrors();
        $e = \App\Models\Expense\Expense::firstOrFail();
        $this->assertSame('submitted', $e->status);

        // Self-approval is off by default.
        $this->post(route('expenses.action', [$e->id, 'approve']))->assertSessionHasErrors('expense');
        $this->post(route('expenses.policy'), ['allow_self_approval' => 1]);
        $this->post(route('expenses.action', [$e->id, 'approve']))->assertSessionHasNoErrors();
        $this->assertSame(-1000.0, $this->balance('employee_payable'));
        $this->post(route('expenses.action', [$e->id, 'reimburse']))->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->balance('employee_payable'));
        $rent = \App\Models\Accounting\Account::where('code', '6020')->firstOrFail();
        $this->assertSame(1000.0, (float) JournalLine::where('account_id', $rent->id)->sum('debit'));
        $this->assertBooksBalance();

        $this->get(route('expenses.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Expenses/Index'));
        $this->get(route('expenses.show', $e->id))->assertOk()->assertInertia(fn ($p) => $p->component('Expenses/Show'));
        $this->get(route('expense-categories.index'))->assertOk();
    }

    public function test_accounting_screens_reports_and_manual_journal(): void
    {
        $cash = app(AccountingService::class)->accountId($this->business->id, 'cash');
        $equity = app(AccountingService::class)->accountId($this->business->id, 'opening_equity');
        $this->post(route('accounting.journals.store'), ['date' => today()->toDateString(), 'memo' => 'Opening cash',
            'lines' => [['account_id' => $cash, 'debit' => 500], ['account_id' => $equity, 'credit' => 400]]])->assertSessionHasErrors('lines');
        $this->post(route('accounting.journals.store'), ['date' => today()->toDateString(), 'memo' => 'Opening cash',
            'lines' => [['account_id' => $cash, 'debit' => 500], ['account_id' => $equity, 'credit' => 500]]])->assertSessionHasNoErrors();
        $this->assertSame(-100.0, $this->balance('cash')); // −600 purchase + 500 opening
        $this->assertBooksBalance();

        foreach (['pl', 'bs', 'tb', 'tax'] as $r) {
            $this->get(route('accounting.reports', ['report' => $r]))->assertOk()->assertInertia(fn ($p) => $p->component('Accounting/Reports'));
        }
        $bs = app(AccountingService::class)->balanceSheet($this->business->id, today()->toDateString());
        $this->assertEqualsWithDelta($bs['assets_total'], $bs['liabilities_total'] + $bs['equity_total'], 0.01);
        $this->get(route('accounting.accounts.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Resource/Index'));
        $this->get(route('accounting.journals.index'))->assertOk();
        $this->get(route('accounting.ledger', $cash))->assertOk()->assertInertia(fn ($p) => $p->component('Accounting/Ledger')->where('closing', -100));
        // System accounts can't be archived.
        $this->delete(route('accounting.accounts.destroy', $cash))->assertSessionHasErrors('archive');
    }

    public function test_analytics_dashboard_and_csv_export(): void
    {
        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 4, 10)])->assertOk();
        $this->get(route('analytics.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Analytics/Index'));
        $summary = app(\App\Services\Analytics\AnalyticsService::class)->summary($this->business->id, today()->subDays(6)->toDateString(), today()->toDateString());
        $this->assertSame(40.0, $summary['kpis']['revenue']['value']);
        $this->assertSame(16.0, $summary['kpis']['gross_profit']['value']); // 40 − 4 × 6
        $csv = $this->get(route('analytics.export', ['report' => 'pos_sales']))->assertOk()->streamedContent();
        $this->assertStringContainsString('invoice_no', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1);
        $this->post(route('analytics.weekly'), ['weekly' => 1])->assertSessionHas('success');
        $this->artisan('analytics:weekly-summaries')->assertSuccessful();
    }
}

