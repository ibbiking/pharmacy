<?php

namespace Tests\Feature\Sales;

use App\Models\InvoiceItemReturn;
use App\Models\NumberSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.8: returns (ReturnService) with stored snapshots, and the invoice / return screens. I-1..I-7. */
class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private $product;
    private array $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
        [$this->product, $this->unit] = $this->makeProduct('Tabs', ['Box' => 1, 'Tablet' => 10], ['Box' => [100, 80], 'Tablet' => [10, 8]]);
        $this->purchase($this->product, $this->unit['Box'], 5, 80, 100, now()->addYear()->toDateString());
    }

    private function returnNo(): int
    {
        return (int) NumberSeries::where('business_id', $this->business->id)->where('document_type', 'return')->value('next_number');
    }

    public function test_refund_takes_off_the_invoice_discount_share_and_is_stored(): void
    {
        // 2 boxes at 100, invoice discount 20 → paid 180.
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Box'], 2, 100)],
            ['invoice_discount_type' => 'amount', 'invoice_discount_value' => 20, 'cash_received' => 200]));
        $item = $invoice->items->first();

        $this->postJson(route('invoices.return-preview', $invoice->invoice_no), ['qty' => [$item->id => 0.5]])
            ->assertOk()->assertJsonPath('refund', 45); // half a box: 50 − 5 share of the invoice discount

        $r = $this->postJson(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 0.5])->assertOk()->json();
        $this->assertEquals(45.0, $r['refund']);
        $row = InvoiceItemReturn::where('return_no', $r['return_no'])->firstOrFail();
        $this->assertEquals([0.5, 100.0, 45.0], [$row->qty_returned, $row->price, $row->refund_amount], 'decimal qty + snapshot');

        // The rest: 1.5 boxes → 150 − 15 = 135; total refunds = what was paid.
        $all = $this->postJson(route('invoices.return', $invoice->invoice_no))->assertOk()->json();
        $this->assertEquals(135.0, $all['refund']);
        $this->assertTrue((bool) $invoice->fresh()->fully_returned);
        $this->assertSame(50.0, $this->remaining(\App\Models\BaseStockSalePrice::firstOrFail()), 'all 20 tablets back in the lot');

        // Reprint comes from the snapshot.
        $this->get(route('returns.print', $r['return_no']))->assertOk()->assertSee('45.00');
        $this->get(route('returns.show', $r['return_no']))->assertInertia(fn (Assert $a) => $a->component('Returns/Show')->where('refund', 45)->where('legacy', false));
    }

    public function test_checks_happen_before_a_number_is_taken(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Box'], 1, 100)]));
        $item = $invoice->items->first();
        $before = $this->returnNo();

        $this->postJson(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 2])->assertStatus(422)->assertJsonValidationErrors("qty.{$item->id}"); // I-4
        $this->assertSame($before, $this->returnNo(), 'no return number used');
        $this->assertSame(0, InvoiceItemReturn::count());

        $this->postJson(route('invoices.return', $invoice->invoice_no))->assertOk();
        $after = $this->returnNo();
        $this->postJson(route('invoices.return', $invoice->invoice_no))->assertStatus(422)->assertJsonValidationErrors('qty'); // I-3
        $this->assertSame($after, $this->returnNo());
        $this->assertSame(1, \App\Models\InvoiceHistory::where('invoice_id', $invoice->id)->where('action', 'Fully Returned')->count());
    }

    public function test_reprint_matches_the_line_not_the_product_and_invoice_page_shows_net_lines(): void
    {
        $this->setPosRule('allow-price-change', true);
        $invoice = $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Tablet'], 4, 10, ['discount_selected_type' => 'amount', 'discount_amount' => 4]),
            $this->line($this->product, $this->unit['Tablet'], 2, 9), // same product, other price
        ]));
        [$a, $b] = [$invoice->items[0], $invoice->items[1]];

        $r = $this->postJson(route('invoices.return-product', [$invoice->invoice_no, $b->id]), ['return_qty' => 1])->assertOk()->json();
        $this->assertEquals(9.0, InvoiceItemReturn::where('return_no', $r['return_no'])->value('price')); // I-7
        $this->post(route('invoices.return-product', [$invoice->invoice_no, $a->id]), ['return_qty' => 2]);

        // I-1: line A had 40 − 4 discount; half returned → 20 − 2 = 18 net now (not 20 − 4).
        $this->get(route('invoices.show', $invoice->invoice_no))->assertOk()->assertInertia(fn (Assert $x) => $x->component('Invoices/Show')
            ->where('lines.0.row_total', 18)->where('lines.0.left', 2)->where('lines.1.left', 1)->has('returns', 2)->where('canReturn', true));
        $this->get(route('invoices.index', ['status' => 'returns']))->assertInertia(fn (Assert $x) => $x->component('Invoices/Index')
            ->where('rows.total', 1)->where('rows.data.0.status', 'partial')->where('stats.returns', 1));
    }
}
