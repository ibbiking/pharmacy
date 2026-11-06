<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.7a: the live POS quote is the checkout engine run read-only, so the screen always matches the invoice. */
class PosQuoteTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    private function quote(array $lines, array $invoice = [])
    {
        return $this->postJson(route('pos.quote'), ['lines' => $lines] + $invoice)->assertOk();
    }

    public function test_quote_matches_the_saved_invoice_and_shows_the_lots(): void
    {
        [$p, $u] = $this->makeProduct('Syrup', ['Box' => 1, 'Bottle' => 10], ['Box' => [1000, 800], 'Bottle' => [100, 80]]);
        $st = $this->tax('Sales Tax', 17);
        $this->purchase($p, $u['Box'], 1, 800, 1000, now()->addYears(2)->toDateString(), [[$st, 17]], batch: 'LATE');
        $this->purchase($p, $u['Box'], 1, 800, 1000, now()->addMonths(6)->toDateString(), [[$st, 17]], batch: 'SOON');
        $this->setPosRule('max-line-discount-percent', true, 20);

        $lines = [
            ['product_id' => $p->id, 'category_id' => $u['Bottle'], 'qty' => 12, 'discount_type' => 'percent', 'discount_percent' => 10], // no price: system
        ];
        $invoice = ['invoice_discount_type' => 'amount', 'invoice_discount_value' => 50, 'cash_received' => 2000];
        $q = $this->quote($lines, $invoice)->json();

        $this->assertTrue($q['can_checkout']);
        $this->assertSame(100.0, (float) $q['lines'][0]['price']);
        $this->assertSame(['SOON', 'LATE'], array_column($q['lines'][0]['lots'], 'batch'), 'FEFO: soonest expiry first, spilling into the next batch');
        $this->assertSame([10.0, 2.0], array_map('floatval', array_column($q['lines'][0]['lots'], 'qty')));

        // The invoice the engine saves has exactly the quoted numbers.
        $lines[0]['price'] = $q['lines'][0]['price'];
        $invoiceModel = $this->invoiceFrom($this->saveInvoice($lines, $invoice));
        $this->assertSame((float) $invoiceModel->grand_total, (float) $q['totals']['grand_total']);
        $this->assertSame((float) $invoiceModel->tax_amount, (float) $q['totals']['additive_tax']);
        $this->assertSame((float) $invoiceModel->change_return, (float) $q['totals']['change']);
    }

    public function test_every_problem_is_reported_per_line_without_stopping(): void
    {
        [$p, $u] = $this->makeProduct('Tabs', ['Box' => 1, 'Tablet' => 10], ['Box' => [100, 80], 'Tablet' => [10, 8]]);
        $this->purchase($p, $u['Box'], 1, 80, 100, now()->addYear()->toDateString());
        $this->setPosRule('allow-price-change', false);
        $this->setPosRule('max-line-discount-percent', true, 5);
        $this->setPosRule('max-invoice-discount-percent', true, 1);

        $q = $this->quote([
            ['product_id' => $p->id, 'category_id' => $u['Tablet'], 'qty' => 15, 'price' => 10],                 // 10 in stock
            ['product_id' => $p->id, 'category_id' => $u['Tablet'], 'qty' => 1, 'price' => 3],                   // price change refused
            ['product_id' => $p->id, 'category_id' => $u['Tablet'], 'qty' => 1, 'discount_type' => 'percent', 'discount_percent' => 50], // over cap
        ], ['invoice_discount_type' => 'percent', 'invoice_discount_value' => 10, 'cash_received' => 1])->json();

        $codes = array_column($q['issues'], 'code', 'line');
        $this->assertFalse($q['can_checkout']);
        $this->assertSame('stock_exceeded', $codes[0]);
        $this->assertSame(10.0, (float) collect($q['issues'])->firstWhere('line', 0)['available']);
        $this->assertSame('price_change_not_allowed', $codes[1]);
        $this->assertSame(10.0, (float) $q['lines'][1]['price'], 'refused price falls back to the system price');
        $this->assertFalse($q['lines'][1]['rules']['can_change_price']);
        $this->assertSame('discount_exceeded', $codes[2]);
        $this->assertSame(5.0, (float) $q['lines'][2]['rules']['max_discount_percent']);
        $this->assertContains('invoice_discount_exceeded', array_column($q['issues'], 'code'));
        $this->assertTrue($q['totals']['cash_short']);
    }

    public function test_cash_must_cover_the_total_on_the_server_and_included_tax_is_owner_only(): void
    {
        [$p, $u] = $this->makeProduct('Syrup', ['Bottle' => 1]);
        $this->purchase($p, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);
        $this->setBusinessPricing('stock-wise-price', includeTax: true);
        $line = [$this->line($p, $u['Bottle'], 1, 117)];

        $this->saveInvoice($line, ['cash_received' => 100])->assertStatus(422)->assertJsonPath('error', 'cash_short');

        $this->quote([['product_id' => $p->id, 'category_id' => $u['Bottle'], 'qty' => 1]])->assertJsonPath('totals.included_tax', 17);
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $cashier);
        $q = $this->quote([['product_id' => $p->id, 'category_id' => $u['Bottle'], 'qty' => 1]])->json();
        $this->assertNull($q['totals']['included_tax']);
        $this->assertSame([], array_values(array_filter($q['taxes'], fn ($t) => $t['kind'] === 'included')));
    }

    public function test_pos_page_and_receipt_printing_from_the_saved_invoice(): void
    {
        $this->get(route('pos.index'))->assertOk()->assertInertia(fn ($a) => $a->component('POS/Index')
            ->where('receipt.name', $this->business->name)->where('defaultLotMode', 'fefo')->has('cartKey'));

        [$p, $u] = $this->makeProduct('Syrup', ['Bottle' => 1], ['Bottle' => [100, 80]]);
        $this->purchase($p, $u['Bottle'], 5, 80, 100, now()->addYear()->toDateString());
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Bottle'], 1, 100)]));

        // The cashier who rang it up can print it; the content is the SAVED invoice.
        // A till-only role (sell, no "View invoices"): the default Sales person role CAN reprint, so make one that can't.
        $tillOnly = \App\Models\BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Till only']);
        $tillOnly->syncPermissions(['pos.sell']);
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => bcrypt('x')]);
        $cashier->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $tillOnly->id]);
        app()->forgetScopedInstances();
        $this->enterBusiness($this->business, $cashier);
        $mine = $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Bottle'], 2, 100)]));
        $this->get(route('pos.receipt', $mine->id))->assertOk()->assertSee($mine->invoice_no)->assertSee('200.00');
        // Someone else's receipt needs the reprint permission ("View invoices").
        $this->get(route('pos.receipt', $invoice->id))->assertForbidden();
        $gone = $this->post('/admin/pos/print-receipt', ['cart' => '[]'])->status(); // browser-data receipts are gone (S-3)
        $this->assertContains($gone, [404, 405]);
    }
}
