<?php

namespace Tests\Feature\Sales;

use App\Models\Accounting\JournalLine;
use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Documents\Payment;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\LedgerPoster;
use App\Services\Sales\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/**
 * Credit in every business type (docs/BUSINESS_TYPES_AND_SERVICES.md, phases 3–4): POS sales on a saved customer's
 * account (paid / part-paid / unpaid), returns as credit notes, purchases paid / part-paid / on credit with their
 * bills, payments, statements and the books.
 */
class CreditFlowsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private $product;
    private array $unit;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Credit Pharmacy');
        app(AccountingService::class)->ensureChart($this->business->id);
        [$this->product, $this->unit] = $this->makeProduct('Panadol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6], 'Box' => [100, 60]]);
        $this->purchase($this->product, $this->unit['Box'], 10, 60, 100, now()->addYear()->toDateString()); // 100 tablets
        $this->customer = Customer::create(['name' => 'Ali Khan', 'payment_terms_days' => 15, 'credit_limit' => 1000]);
    }

    private function balance(string $key, ?int $customerId = null): float
    {
        $l = JournalLine::where('account_id', app(AccountingService::class)->accountId($this->business->id, $key))
            ->when($customerId, fn ($q) => $q->where('contact_type', 'customer')->where('contact_id', $customerId));

        return round((float) $l->sum('debit') - (float) (clone $l)->sum('credit'), 2);
    }

    private function assertBooksBalance(): void
    {
        $this->assertSame(round((float) JournalLine::sum('debit'), 2), round((float) JournalLine::sum('credit'), 2), 'Debits must equal credits');
    }

    private function sellOnCredit(float $boxes, float $paid, array $extra = []): Invoice
    {
        return $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Box'], $boxes, 100)],
            ['customer_id' => $this->customer->id, 'on_credit' => true, 'cash_received' => $paid] + $extra));
    }

    public function test_part_paid_sale_goes_on_the_customers_account(): void
    {
        $invoice = $this->sellOnCredit(3, 100); // 300, paid 100 → owes 200

        $this->assertSame('partial', $invoice->payment_status);
        $this->assertSame($this->customer->id, (int) $invoice->customer_id);
        $doc = Document::where('origin', 'pos')->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('sales_invoice', $doc->type);
        $this->assertSame('partial', $doc->status);
        $this->assertEquals(200, $doc->balance);
        $this->assertSame(today()->addDays(15)->toDateString(), $doc->due_date->toDateString());
        $this->assertEquals(100, Payment::where('customer_id', $this->customer->id)->sum('amount'));
        $this->assertEquals(200, app(\App\Services\Credit\CreditService::class)->customerBalance($this->customer->id));

        // Books: customer owes 200, cash 100 in, sale 300; no double posting by the linked document.
        $this->assertEquals(200, $this->balance('ar', $this->customer->id));
        $this->assertBooksBalance();
        $this->assertSame(0, JournalLine::whereHas('entry', fn ($q) => $q->where('source_type', 'document')->where('source_id', $doc->id))->count());
    }

    public function test_unpaid_sale_then_payment_received_settles_it(): void
    {
        $invoice = $this->sellOnCredit(2, 0);
        $doc = Document::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('unpaid', $invoice->payment_status);

        $this->post(route('selling.payments.store'), ['customer_id' => $this->customer->id, 'date' => today()->toDateString(), 'amount' => 200, 'method' => 'cash',
            'allocations' => [['document_id' => $doc->id, 'amount' => 200]]])->assertRedirect();

        $this->assertSame('paid', $doc->fresh()->status);
        $this->assertEquals(0, $this->balance('ar', $this->customer->id));
        $this->assertBooksBalance();
        // Statement and ageing are open to a pharmacy (Customer credit micro service), not only to Wholesale.
        $this->get(route('customers.statement', $this->customer->id))->assertOk()->assertSee($invoice->invoice_no);
        $this->get(route('reports.ar_aging'))->assertOk();
    }

    public function test_credit_is_only_for_saved_customers_and_within_the_limit(): void
    {
        // Walk-in (no customer) on credit: refused.
        $this->saveInvoice([$this->line($this->product, $this->unit['Box'], 1, 100)], ['on_credit' => true, 'cash_received' => 0])
            ->assertStatus(422)->assertJsonPath('error', 'customer_required');
        // Limit 600: 500 owed is fine; 200 more (700) is refused and nothing is saved.
        $this->customer->update(['credit_limit' => 600]);
        $before = Invoice::count();
        $this->saveInvoice([$this->line($this->product, $this->unit['Box'], 5, 100)], ['customer_id' => $this->customer->id, 'on_credit' => true, 'cash_received' => 0])->assertOk();
        $this->saveInvoice([$this->line($this->product, $this->unit['Box'], 2, 100)], ['customer_id' => $this->customer->id, 'on_credit' => true, 'cash_received' => 0])
            ->assertStatus(422)->assertJsonPath('error', 'credit_limit');
        $this->assertSame($before + 1, Invoice::count());
        // Without credit a saved customer still pays in full.
        $this->saveInvoice([$this->line($this->product, $this->unit['Box'], 1, 100)], ['customer_id' => $this->customer->id, 'cash_received' => 50])
            ->assertStatus(422)->assertJsonPath('error', 'cash_short');
    }

    public function test_credit_needs_the_customer_credit_module(): void
    {
        $this->delete(route('modules.deactivate', 'customer_credit'))->assertSessionHas('success');
        app(\App\Services\Modules\ModuleManager::class)->forget();
        $this->saveInvoice([$this->line($this->product, $this->unit['Box'], 2, 100)], ['customer_id' => $this->customer->id, 'on_credit' => true, 'cash_received' => 0])
            ->assertStatus(422)->assertJsonPath('error', 'credit_not_allowed');
    }

    public function test_the_live_quote_shows_what_goes_on_the_account(): void
    {
        $this->postJson(route('pos.quote'), ['lines' => [$this->line($this->product, $this->unit['Box'], 3, 100)], 'cash_received' => 50,
            'customer_id' => $this->customer->id, 'on_credit' => true])->assertOk()
            ->assertJsonPath('totals.cash_short', false)
            ->assertJsonPath('totals.credit.paid_now', 50)
            ->assertJsonPath('totals.credit.on_account', 250)
            ->assertJsonPath('totals.credit.over_limit', false);
    }

    public function test_returning_a_credit_sale_makes_a_credit_note_not_a_cash_refund(): void
    {
        $invoice = $this->sellOnCredit(3, 100); // owes 200
        $item = $invoice->items->first();
        $result = app(ReturnService::class)->return($invoice->fresh(), [$item->id => 1], 'Wrong item', $this->owner->id);

        $this->assertTrue($result['credited']);
        $doc = Document::where('origin', 'pos')->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals(100, $doc->balance, '200 owed − 100 credited');
        $credit = Document::where('origin', 'pos_return')->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('credit_note', $credit->type);
        $this->assertSame('applied', $credit->status);
        $this->assertEquals(100, $this->balance('ar', $this->customer->id));
        $this->assertBooksBalance();

        // Return more than is still owed: the rest stays as the customer's credit.
        app(ReturnService::class)->return($invoice->fresh(), [$item->id => 2], null, $this->owner->id);
        $this->assertSame('paid', $doc->fresh()->status);
        $this->assertEquals(-100, app(\App\Services\Credit\CreditService::class)->customerBalance($this->customer->id));
        $this->assertBooksBalance();
    }

    public function test_purchase_paid_partly_paid_or_on_credit_gets_its_bill(): void
    {
        $line = fn () => ['product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 5, 'unit_cost_price' => 60, 'unit_sale_price' => 100,
            'expiry_date' => now()->addYear()->toDateString()];
        $buy = fn (array $extra) => $this->post(route('purchases.store'), ['supplier' => ['id' => $this->supplier->id], 'invoice_no' => $extra['invoice_no'], 'lines' => [$line()]] + $extra);

        $buy(['invoice_no' => 'SUP-1', 'payment' => 'paid'])->assertSessionHasNoErrors();
        $this->assertSame('paid', Purchase::where('invoice_no', 'SUP-1')->value('payment_status'));
        $this->assertFalse(Document::where('origin', 'purchase')->where('number', 'SUP-1')->exists(), 'paid in full: no bill to track');

        $buy(['invoice_no' => 'SUP-2', 'payment' => 'partial', 'amount_paid' => 100])->assertSessionHasNoErrors();
        $bill = Document::where('origin', 'purchase')->where('number', 'SUP-2')->firstOrFail();
        $this->assertSame('bill', $bill->type);
        $this->assertSame('partial', $bill->status);
        $this->assertEquals(300, $bill->total);
        $this->assertEquals(200, $bill->balance);
        $this->assertSame('partial', Purchase::where('invoice_no', 'SUP-2')->value('payment_status'));

        $buy(['invoice_no' => 'SUP-3', 'payment' => 'unpaid'])->assertSessionHasNoErrors();
        $this->assertSame('unpaid', Document::where('origin', 'purchase')->where('number', 'SUP-3')->value('status'));

        $buy(['invoice_no' => 'SUP-4', 'payment' => 'partial', 'amount_paid' => 300])->assertSessionHasErrors('amount_paid');

        // Owed to the supplier: 200 + 300; the books agree; statement and ageing work for a pharmacy.
        $ap = app(AccountingService::class)->accountId($this->business->id, 'ap');
        $this->assertEquals(-500, round((float) JournalLine::where('account_id', $ap)->sum('debit') - (float) JournalLine::where('account_id', $ap)->sum('credit'), 2));
        $this->assertBooksBalance();
        $this->get(route('suppliers.statement', $this->supplier->id))->assertOk()->assertSee('SUP-2');
        $this->get(route('reports.ap_aging'))->assertOk();

        // Pay the rest of SUP-2 from Buying → Payments.
        $this->post(route('buying.payments.store'), ['supplier_id' => $this->supplier->id, 'date' => today()->toDateString(), 'amount' => 200, 'method' => 'bank',
            'allocations' => [['document_id' => $bill->id, 'amount' => 200]]])->assertRedirect();
        $this->assertSame('paid', $bill->fresh()->status);

        // A purchase whose bill has payments is locked from edits (amounts would no longer match the bill).
        $this->assertNotNull(app(\App\Services\Stock\PurchaseService::class)->lockReason(Purchase::where('invoice_no', 'SUP-2')->first()));
    }

    public function test_backfill_posts_credit_sales_and_purchases_the_same_way(): void
    {
        $this->sellOnCredit(3, 100);
        $this->post(route('purchases.store'), ['supplier' => ['id' => $this->supplier->id], 'invoice_no' => 'SUP-9', 'payment' => 'unpaid',
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Box'], 'quantity' => 2, 'unit_cost_price' => 60, 'unit_sale_price' => 100,
                'expiry_date' => now()->addYear()->toDateString()]]])->assertSessionHasNoErrors();
        $ar = $this->balance('ar', $this->customer->id);
        $lines = JournalLine::count();

        app(LedgerPoster::class)->backfill($this->business->id);

        $this->assertSame($ar, $this->balance('ar', $this->customer->id), 'backfill is idempotent');
        $this->assertSame($lines, JournalLine::count());
        $this->assertBooksBalance();
    }

    public function test_wholesale_pos_needs_a_customer(): void
    {
        $this->setUpBusiness('Wholesale', 'inventory_b2b');
        [$p, $u] = $this->makeProduct('Rice', ['Bag' => 1]);
        $this->purchase($p, $u['Bag'], 10, 50, 80, now()->addYear()->toDateString());

        $this->saveInvoice([$this->line($p, $u['Bag'], 1, 80)])->assertStatus(422)->assertJsonPath('error', 'customer_required');
        $buyer = Customer::create(['name' => 'Shop One']);
        $this->saveInvoice([$this->line($p, $u['Bag'], 1, 80)], ['customer_id' => $buyer->id])->assertOk();
    }
}
