<?php

namespace Tests\Feature\Modules;

use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Documents\Payment;
use App\Models\Documents\Shipment;
use App\Models\Accounting\JournalLine;
use App\Services\Accounting\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/**
 * Wholesale & Distribution (phase 5, docs/BUSINESS_TYPES_AND_SERVICES.md): invoices / bills marked paid, part paid or
 * unpaid when issued, shipping with a delivery note, linked documents only payable, B2B screens absent elsewhere.
 */
class WholesaleFlowsTest extends TestCase
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
        $this->setUpBusiness('Wholesale', 'inventory_b2b');
        app(AccountingService::class)->ensureChart($this->business->id);
        [$this->product, $this->unit] = $this->makeProduct('Rice', ['Bag' => 1, 'Kg' => 5], ['Kg' => [20, 12], 'Bag' => [100, 60]]);
        $this->purchase($this->product, $this->unit['Bag'], 20, 60, 100, now()->addYear()->toDateString());
        $this->customer = Customer::create(['name' => 'Corner Store', 'payment_terms_days' => 30, 'address' => 'Main Road']);
    }

    private function draftInvoice(int $bags = 2): Document
    {
        $this->post(route('selling.invoices.store'), ['customer_id' => $this->customer->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Bag'], 'quantity' => $bags]]])->assertSessionHasNoErrors();

        return Document::where('type', 'sales_invoice')->latest('id')->firstOrFail();
    }

    public function test_an_invoice_issued_with_part_payment_is_partly_paid(): void
    {
        $invoice = $this->draftInvoice(2); // 200
        $this->post(route('selling.docs.action', [$invoice->id, 'issue']), ['paid_now' => 50, 'method' => 'bank'])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertEquals(150, $invoice->balance);
        $this->assertSame('bank', Payment::where('customer_id', $this->customer->id)->value('method'));
        $this->assertSame(round((float) JournalLine::sum('debit'), 2), round((float) JournalLine::sum('credit'), 2));
    }

    public function test_an_invoice_issued_paid_in_full_or_on_credit(): void
    {
        $paid = $this->draftInvoice(1);
        $this->post(route('selling.docs.action', [$paid->id, 'issue']), ['paid_now' => 100])->assertSessionHasNoErrors();
        $this->assertSame('paid', $paid->fresh()->status);

        $credit = $this->draftInvoice(1);
        $this->post(route('selling.docs.action', [$credit->id, 'issue']))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $credit->fresh()->status);
    }

    public function test_shipping_a_sales_invoice_with_a_delivery_note(): void
    {
        $draft = $this->draftInvoice(1);
        $this->post(route('selling.shipments.store', $draft->id), ['carrier' => 'TCS'])->assertSessionHasErrors('shipment'); // not issued yet

        $this->post(route('selling.docs.action', [$draft->id, 'issue']))->assertSessionHasNoErrors();
        $this->post(route('selling.shipments.store', $draft->id), ['carrier' => 'TCS', 'tracking_no' => 'TX-1', 'packages' => 2, 'weight_kg' => 10.5])->assertSessionHasNoErrors();
        $s = Shipment::firstOrFail();
        $this->assertSame('packed', $s->status);
        $this->assertSame('SHP-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT), $s->number);

        $this->post(route('selling.shipments.advance', [$s->id, 'delivered']))->assertSessionHasErrors('shipment'); // must ship first
        $this->post(route('selling.shipments.advance', [$s->id, 'shipped']))->assertSessionHasNoErrors();
        $this->post(route('selling.shipments.advance', [$s->id, 'delivered']))->assertSessionHasNoErrors();
        $this->assertSame('delivered', $s->fresh()->status);
        $this->assertNotNull($s->fresh()->delivered_at);
        $this->delete(route('selling.shipments.destroy', $s->id))->assertSessionHasErrors('shipment'); // kept as a record

        $this->get(route('selling.shipments.print', $s->id))->assertOk()->assertSee('Delivery note')->assertSee('TX-1')->assertSee('Corner Store');
        $this->get(route('selling.docs.show', $draft->id))->assertInertia(fn (Assert $page) => $page->has('shipments', 1)->where('shipments.0.status', 'delivered'));
    }

    public function test_a_purchase_bill_made_by_a_purchase_only_offers_payment(): void
    {
        $this->post(route('purchases.store'), ['supplier' => ['id' => $this->supplier->id], 'invoice_no' => 'VB-7', 'payment' => 'unpaid',
            'lines' => [['product_id' => $this->product->id, 'category_id' => $this->unit['Bag'], 'quantity' => 3, 'unit_cost_price' => 60, 'unit_sale_price' => 100,
                'expiry_date' => now()->addYear()->toDateString()]]])->assertSessionHasNoErrors();
        $bill = Document::where('origin', 'purchase')->firstOrFail();

        $this->get(route('buying.docs.show', $bill->id))->assertInertia(fn (Assert $page) => $page
            ->where('document.origin.label', 'Purchase')
            ->where('actions', fn ($a) => collect($a)->pluck('key')->all() === ['pay']));
        // The Bills list shows it next to bills from purchase orders.
        $this->get(route('buying.bills.index'))->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    }

    public function test_b2b_screens_and_menus_do_not_exist_for_a_pharmacy(): void
    {
        $this->setUpBusiness('Chemist', 'pharmacy');
        $this->get(route('selling.quotations.index'))->assertForbidden();
        $this->get(route('buying.orders.index'))->assertForbidden();
        $nav = collect($this->get(route('modules.index'))->viewData('page')['props']['navigation'])->flatMap(fn ($g) => $g['items'])->pluck('label');
        $this->assertNotContains('Sales documents', $nav);
        $this->assertNotContains('Purchase documents', $nav);
        $this->assertContains('Customer accounts', $nav);
        $this->assertContains('Supplier accounts', $nav);
        $this->assertContains('POS', $nav);
    }

    public function test_the_wholesale_pos_is_the_invoice_screen(): void
    {
        $this->get(route('pos.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('mode', 'invoice')->where('credit.enabled', true));
        $nav = collect($this->get(route('modules.index'))->viewData('page')['props']['navigation'])->flatMap(fn ($g) => $g['items'])->pluck('label');
        $this->assertContains('Invoice', $nav);
        $this->assertNotContains('POS', $nav);
    }
}
