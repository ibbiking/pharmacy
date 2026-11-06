<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceItemLot;
use App\Models\Preference;
use App\Models\ProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/**
 * Roadmap step 2: sale engine correctness.
 *
 * Fixture used by most tests — product "Paracetamol": Box → 1 Strip → 14 Tablets (base unit).
 * Static prices: Box/Strip 550, Tablet 40.
 *   lotOld  : bought first, 1 Box, cost 400, sale 560 (40/tablet), expires in 2 years
 *   lotSoon : bought second, 1 Box, cost 420, sale 588 (42/tablet), expires in 3 months
 * FIFO (oldest purchase) sells lotOld first; FEFO (earliest expiry) sells lotSoon first.
 */
class SaleEngineTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private $product;
    private array $unit;
    private $lotOld;
    private $lotSoon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();

        [$this->product, $this->unit] = $this->makeProduct('Paracetamol', ['Box' => 1, 'Strip' => 1, 'Tablet' => 14], [
            'Box' => [550, 400], 'Strip' => [550, 400], 'Tablet' => [40, 28.57],
        ]);
        $this->lotOld = $this->purchase($this->product, $this->unit['Box'], 1, 400, 560, now()->addYears(2)->toDateString());
        $this->lotSoon = $this->purchase($this->product, $this->unit['Box'], 1, 420, 588, now()->addMonths(3)->toDateString());
    }

    // ------------------------------------------------------------------ preferences

    public function test_every_new_business_gets_its_business_wide_preferences(): void
    {
        $other = Business::create(['name' => 'Second Shop']);

        foreach ([$this->business->id, $other->id] as $businessId) {
            $rows = Preference::withoutGlobalScopes()->where('business_id', $businessId)->get()->keyBy(fn ($r) => $r->type.':'.$r->slug);
            $this->assertCount(10, $rows, "business {$businessId}");
            $this->assertTrue((bool) $rows['sale_price:static-price']->status);
            $this->assertFalse((bool) $rows['sale_price:stock-wise-price']->status);
            $this->assertTrue((bool) $rows['lot_selection:fefo']->status);
            $this->assertTrue((bool) $rows['pos:allow-price-change']->status, 'price change stays allowed by default (as before)');
        }
    }

    public function test_static_price_per_packaging_unit(): void
    {
        $this->setBusinessPricing('static-price');

        $this->checkStock($this->product, 1, $this->unit['Tablet'])->assertJsonPath('rows.0.unit_price', 40);
        $this->checkStock($this->product, 1, $this->unit['Box'])->assertJsonPath('rows.0.unit_price', 550);
    }

    public function test_stock_wise_price_follows_the_lot_order(): void
    {
        $this->setBusinessPricing('stock-wise-price');

        $this->setBusinessLotMode('fifo');
        $this->assertEquals(40.0, $this->checkStock($this->product, 1, $this->unit['Tablet'])->json('rows.0.unit_price'));

        $this->setBusinessLotMode('fefo');
        $this->assertEquals(42.0, $this->checkStock($this->product, 1, $this->unit['Tablet'])->json('rows.0.unit_price'));
        $this->assertEquals(588.0, $this->checkStock($this->product, 1, $this->unit['Box'])->json('rows.0.unit_price'));
    }

    public function test_previous_inventory_price_uses_the_latest_purchase(): void
    {
        $this->setBusinessPricing('previous-inventory-price');

        $this->assertEquals(42.0, (float) $this->checkStock($this->product, 1, $this->unit['Tablet'])->json('rows.0.unit_price'));
        $this->assertEquals(588.0, (float) $this->checkStock($this->product, 1, $this->unit['Strip'])->json('rows.0.unit_price'));
    }

    public function test_product_preference_overrides_the_business_preference(): void
    {
        $this->setBusinessPricing('static-price');
        $this->setProductPricing($this->product, 'previous-inventory-price');

        $this->assertEquals(42.0, (float) $this->checkStock($this->product, 1, $this->unit['Tablet'])->json('rows.0.unit_price'));
    }

    public function test_including_tax_puts_the_lot_sale_tax_inside_the_price(): void
    {
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);

        $this->setBusinessPricing('previous-inventory-price', includeTax: false);
        $this->assertEquals(100.0, (float) $this->checkStock($taxed, 1, $u['Bottle'])->json('rows.0.unit_price'));

        $this->setBusinessPricing('previous-inventory-price', includeTax: true);
        $this->assertEquals(117.0, (float) $this->checkStock($taxed, 1, $u['Bottle'])->json('rows.0.unit_price'));
    }

    // ------------------------------------------------------------------ checkout: stock & lots

    public function test_checkout_takes_earliest_expiry_first_by_default_and_records_the_lots(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 16, 40)]));

        $this->assertEquals(0.0, $this->remaining($this->lotSoon));
        $this->assertEquals(12.0, $this->remaining($this->lotOld));

        $item = $invoice->items->first();
        $this->assertSame('fefo', $item->lot_selection_mode);
        $this->assertEquals([$this->lotSoon->id => 14.0, $this->lotOld->id => 2.0],
            $item->lots->mapWithKeys(fn ($l) => [$l->base_stock_sale_price_id => (float) $l->base_qty])->all());
        $this->assertEquals(28 - 16, (float) ProductStock::where('product_id', $this->product->id)->value('current_stock'));
    }

    public function test_a_line_can_choose_oldest_purchase_first(): void
    {
        $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Tablet'], 3, 40, ['lot_selection_mode' => 'fifo']),
        ]));

        $this->assertEquals(11.0, $this->remaining($this->lotOld));
        $this->assertEquals(14.0, $this->remaining($this->lotSoon));
    }

    public function test_two_products_with_the_same_price_each_lose_their_own_stock(): void
    {
        // Bug B1: lines were grouped by price only, so product B's quantity came out of product A.
        [$other, $u] = $this->makeProduct('Ibuprofen', ['Box' => 1, 'Strip' => 1, 'Tablet' => 14], ['Tablet' => [40, 25]]);
        $otherLot = $this->purchase($other, $u['Box'], 1, 350, 560, now()->addYear()->toDateString());

        $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Tablet'], 2, 40),
            $this->line($other, $u['Tablet'], 5, 40),
        ]));

        $this->assertEquals(28 - 2, $this->remaining($this->lotOld) + $this->remaining($this->lotSoon));
        $this->assertEquals(14 - 5, $this->remaining($otherLot));
    }

    public function test_the_browser_base_quantity_is_ignored(): void
    {
        // Bug B2: 1 Box = 14 tablets, whatever base_qty the browser sends.
        $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Box'], 1, 550, ['base_qty' => 1]),
        ]));

        $this->assertEquals(14.0, $this->remaining($this->lotSoon) + $this->remaining($this->lotOld));
    }

    public function test_overselling_is_rejected_and_nothing_changes(): void
    {
        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 29, 40)])
            ->assertStatus(400)
            ->assertJsonPath('error', 'stock_exceeded')
            ->assertJsonPath('available_category_qty', 28);

        $this->assertEquals(14.0, $this->remaining($this->lotOld));
        $this->assertEquals(14.0, $this->remaining($this->lotSoon));
        $this->assertSame(0, Invoice::count());
    }

    public function test_expired_lots_are_never_sold_or_used_for_tax(): void
    {
        $vat = $this->tax('VAT', 10);
        $expired = $this->purchase($this->product, $this->unit['Box'], 1, 300, 560, now()->subDay()->toDateString(), [[$vat, 10]]);

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 40)]));

        $this->assertEquals(14.0, $this->remaining($expired));
        $this->assertCount(0, $invoice->items->first()->taxes, 'tax came from the expired lot (bug B4)');
        $this->assertEquals(80.0, (float) $invoice->grand_total);
    }

    public function test_stock_wise_cart_split_per_lot_is_sold_from_exactly_those_lots(): void
    {
        $this->setBusinessPricing('stock-wise-price');

        $rows = $this->checkStock($this->product, 16, $this->unit['Tablet'])->assertJsonPath('status', 'ok')->json('rows');
        $this->assertCount(2, $rows);
        $this->assertEquals([42.0, 40.0], array_map(fn ($r) => (float) $r['unit_price'], $rows));

        $invoice = $this->invoiceFrom($this->saveInvoice($this->linesFromRows($this->product, $rows)));

        $this->assertEquals(0.0, $this->remaining($this->lotSoon));
        $this->assertEquals(12.0, $this->remaining($this->lotOld));
        $this->assertEquals(14 * 42 + 2 * 40, (float) $invoice->grand_total);
    }

    // ------------------------------------------------------------------ checkout: price & discount rules (D2)

    public function test_price_change_is_rejected_when_not_allowed(): void
    {
        $this->setPosRule('allow-price-change', false);

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 1, 1.00)])
            ->assertStatus(422)
            ->assertJsonPath('error', 'price_change_not_allowed')
            ->assertJsonPath('context.server_price', 40);

        $this->assertSame(0, Invoice::count());
        $this->assertEquals(28.0, $this->remaining($this->lotOld) + $this->remaining($this->lotSoon));
    }

    public function test_allowed_price_change_is_saved_and_audited(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 35)]));
        $item = $invoice->items->first();

        $this->assertEquals(35.0, (float) $item->price);
        $this->assertEquals(40.0, (float) $item->server_unit_price);
        $this->assertTrue((bool) $item->price_overridden);
        $this->assertEquals(70.0, (float) $invoice->grand_total);
        $this->assertTrue(InvoiceHistory::where('invoice_id', $invoice->id)->where('action', 'Price Override')->exists());
    }

    public function test_price_below_the_minimum_is_rejected(): void
    {
        $this->setPosRule('min-price-percent', true, '90');

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 1, 35)])
            ->assertStatus(422)->assertJsonPath('error', 'price_below_minimum')->assertJsonPath('context.minimum_price', 36);

        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 1, 36)]));
    }

    public function test_product_can_allow_price_change_when_the_business_does_not(): void
    {
        $this->setPosRule('allow-price-change', false);
        $this->product->update(['allow_price_change' => true]);
        $this->forgetResolvedSettings();

        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 1, 39)]));
    }

    public function test_line_discount_above_the_product_cap_is_rejected(): void
    {
        $this->product->update(['lock_max_discount' => true, 'discount_percent' => 5]);
        $this->forgetResolvedSettings();

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 10, 40, ['discount_percent' => 10])])
            ->assertStatus(422)->assertJsonPath('error', 'discount_exceeded');

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 10, 40, ['discount_percent' => 5])]));
        $this->assertEquals(380.0, (float) $invoice->grand_total);
    }

    public function test_business_invoice_discount_cap(): void
    {
        $this->setPosRule('max-invoice-discount-percent', true, '10');

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 10, 40)], ['invoice_discount_type' => 'percent', 'invoice_discount_value' => 15])
            ->assertStatus(422)->assertJsonPath('error', 'invoice_discount_exceeded');
    }

    public function test_invoice_discount_and_totals_are_computed_by_the_server(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice(
            [$this->line($this->product, $this->unit['Tablet'], 10, 40)],
            ['invoice_discount_type' => 'percent', 'invoice_discount_value' => 10, 'invoice_discount_amount' => 999, 'cash_received' => 500]
        ));

        $this->assertEquals(400.0, (float) $invoice->subtotal);
        $this->assertEquals(40.0, (float) $invoice->invoice_discount_amount);
        $this->assertEquals(360.0, (float) $invoice->grand_total);
        $this->assertEquals(140.0, (float) $invoice->change_return);
    }

    // ------------------------------------------------------------------ taxes (D3)

    public function test_named_sale_tax_is_added_on_top_when_prices_exclude_tax(): void
    {
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1], ['Bottle' => [100, 80]]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($taxed, $u['Bottle'], 2, 100)]));

        $this->assertEquals(34.0, (float) $invoice->tax_amount);
        $this->assertEquals(0.0, (float) $invoice->included_tax_amount);
        $this->assertEquals(234.0, (float) $invoice->grand_total);
        $this->assertFalse((bool) $invoice->items->first()->taxes->first()->is_included);
    }

    public function test_tax_inclusive_price_is_not_charged_twice_and_is_owner_only(): void
    {
        // Bug P3 / decision D3.
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);
        $this->setBusinessPricing('stock-wise-price', includeTax: true);

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($taxed, $u['Bottle'], 1, 117)]));

        $this->assertEquals(117.0, (float) $invoice->grand_total, 'customer pays the shown price');
        $this->assertEquals(0.0, (float) $invoice->tax_amount);
        $this->assertEquals(17.0, (float) $invoice->included_tax_amount);
        $this->assertTrue((bool) $invoice->items->first()->taxes->first()->is_included);
        $this->assertSame([], $invoice->consolidatedTaxBreakdown(), 'customer receipt hides included tax');
        $this->assertCount(1, $invoice->consolidatedTaxBreakdown(withIncludedTax: true));
    }

    public function test_cart_tax_preview_matches_checkout(): void
    {
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1], ['Bottle' => [100, 80]]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]], extraPaidPercent: 5);

        $preview = $this->postJson(route('pos.cart-tax-breakdown'), ['cart' => [$this->line($taxed, $u['Bottle'], 2, 100)]])->assertOk();
        $preview->assertJsonPath('salesTaxTotal', 34)->assertJsonPath('gstTotal', 10);

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($taxed, $u['Bottle'], 2, 100)]));
        $this->assertEquals(34.0, (float) $invoice->tax_amount);
        $this->assertEquals(10.0, (float) $invoice->items->first()->tax_amount, 'GST disclosure');
        $this->assertEquals(234.0, (float) $invoice->grand_total, 'GST is never added');
    }

    // ------------------------------------------------------------------ returns

    public function test_partial_return_restocks_the_exact_lots_sold(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 16, 40)]));
        $item = $invoice->items->first();

        $this->post(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 3])->assertRedirect();

        // Most recently consumed lot first: lotOld got its 2 back, then 1 to lotSoon.
        $this->assertEquals(14.0, $this->remaining($this->lotOld));
        $this->assertEquals(1.0, $this->remaining($this->lotSoon));
        $this->assertEquals(3.0, (float) InvoiceItemLot::where('invoice_item_id', $item->id)->sum('returned_base_qty'));
    }

    public function test_full_return_after_a_partial_return_refunds_the_right_amount(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Tablet'], 10, 40, ['discount_selected_type' => 'amount', 'discount_amount' => 10]),
        ]));
        $item = $invoice->items->first();

        $this->post(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 4])->assertRedirect();
        $response = $this->post(route('invoices.return', $invoice->invoice_no))->assertRedirect();

        // Remaining 6 tablets × 40 minus the remaining 6.00 discount.
        $this->assertEquals(234.0, round((float) $response->getSession()->get('return')['refund'], 2));
        $this->assertEquals(234.0, (float) \App\Models\InvoiceItemReturn::where('return_no', $response->getSession()->get('return')['return_no'])->sum('refund_amount'), 'stored snapshot');
        $this->assertEquals(28.0, $this->remaining($this->lotOld) + $this->remaining($this->lotSoon));
        $this->assertTrue((bool) $invoice->fresh()->fully_returned);
    }

    public function test_returning_a_tax_inclusive_line_does_not_refund_the_tax_twice(): void
    {
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);
        $this->setBusinessPricing('stock-wise-price', includeTax: true);

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($taxed, $u['Bottle'], 2, 117)]));
        $item = $invoice->items->first();

        $response = $this->post(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 1])->assertRedirect();
        $result = $response->getSession()->get('return');
        $snapshot = \App\Models\InvoiceItemReturn::where('return_no', $result['return_no'])->firstOrFail();

        $this->assertEquals(117.0, round((float) $result['refund'], 2));
        $this->assertSame([], array_values(array_filter($snapshot->taxes, fn ($t) => $t['kind'] !== 'included')), 'nothing refunded on top');
        $this->assertEquals(17.0, (float) $invoice->fresh()->included_tax_amount);
        $this->assertEquals(117.0, (float) $invoice->fresh()->grand_total);
    }

    // ------------------------------------------------------------------ reports

    public function test_profit_and_loss_uses_the_actual_cost_of_the_lots_sold(): void
    {
        // 14 tablets from lotSoon (420/14 = 30 each) + 2 from lotOld (400/14 each).
        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 16, 40)]));

        $this->get(route('reports.profit_loss'))->assertOk()->assertInertia(function (\Inertia\Testing\AssertableInertia $page) {
            $kpis = $page->toArray()['props']['report']['kpis'];
            $this->assertEqualsWithDelta(14 * 30 + 2 * (400 / 14), $kpis['cost'], 0.01);
            $this->assertEqualsWithDelta(640, $kpis['revenue'], 0.01);
        });
    }
}
