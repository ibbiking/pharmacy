<?php

namespace Tests\Feature\Sales;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\PosCartReservation;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 2: edge cases around the sale engine (concurrency, tenancy, FBR, data migration). */
class SaleEngineEdgeCasesTest extends TestCase
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
        $this->lotOld = $this->purchase($this->product, $this->unit['Box'], 1, 400, 560, now()->addYears(2)->toDateString(), batch: 'B-OLD');
        $this->lotSoon = $this->purchase($this->product, $this->unit['Box'], 1, 420, 588, now()->addMonths(3)->toDateString(), batch: 'B-SOON');
    }

    public function test_stock_held_in_another_cashiers_cart_cannot_be_sold(): void
    {
        $cashier = User::create(['name' => 'Cashier 2', 'email' => 'c2@example.test', 'password' => bcrypt('x')]);
        PosCartReservation::create([
            'business_id' => $this->business->id, 'user_id' => $cashier->id, 'product_id' => $this->product->id,
            'category_id' => $this->unit['Tablet'], 'price_group' => '40.00', 'base_qty' => 20,
        ]);

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 10, 40)])
            ->assertStatus(400)->assertJsonPath('available_category_qty', 8);

        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 8, 40)]));
    }

    public function test_a_business_cannot_sell_another_business_product(): void
    {
        $intruder = User::create(['name' => 'Other owner', 'email' => 'other@example.test', 'password' => bcrypt('x')]);
        $otherBusiness = Business::create(['name' => 'Other Shop']);
        $intruder->businesses()->attach($otherBusiness->id, ['role' => 'owner']);
        $this->enterBusiness($otherBusiness, $intruder);

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 1, 40)])
            ->assertStatus(422)->assertJsonPath('error', 'invalid_line');

        $this->assertEquals(28.0, $this->remaining($this->lotOld) + $this->remaining($this->lotSoon));
    }

    public function test_mixed_units_of_one_product_in_one_cart(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Box'], 1, 550),
            $this->line($this->product, $this->unit['Tablet'], 5, 40),
        ]));

        $this->assertEquals(28 - 19, $this->remaining($this->lotOld) + $this->remaining($this->lotSoon));
        $this->assertEquals(750.0, (float) $invoice->grand_total);
        $this->assertEquals(19.0, (float) $invoice->items->sum(fn ($i) => $i->lots->sum('base_qty')));
    }

    public function test_a_lot_id_from_another_product_is_ignored(): void
    {
        [$other, $u] = $this->makeProduct('Other', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 5]]);
        $foreignLot = $this->purchase($other, $u['Box'], 1, 50, 100, now()->addYear()->toDateString());

        $this->invoiceFrom($this->saveInvoice([
            $this->line($this->product, $this->unit['Tablet'], 2, 40, ['base_stock_sale_price_id' => $foreignLot->id]),
        ]));

        $this->assertEquals(10.0, $this->remaining($foreignLot));
        $this->assertEquals(12.0, $this->remaining($this->lotSoon));
    }

    public function test_included_tax_is_hidden_from_sales_persons_in_the_preview(): void
    {
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);
        $this->setBusinessPricing('stock-wise-price', includeTax: true);
        $cart = ['cart' => [$this->line($taxed, $u['Bottle'], 1, 117)]];

        $this->postJson(route('pos.cart-tax-breakdown'), $cart)
            ->assertJsonPath('salesTaxTotal', 0)->assertJsonPath('includedTaxTotal', 17);

        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $cashier);

        $response = $this->postJson(route('pos.cart-tax-breakdown'), $cart)->assertOk();
        $this->assertArrayNotHasKey('includedTaxTotal', $response->json());
        $this->assertSame(0, $response->json('salesTaxTotal'));
    }

    public function test_full_return_refunds_additive_sale_tax(): void
    {
        [$taxed, $u] = $this->makeProduct('Syrup', ['Bottle' => 1], ['Bottle' => [100, 80]]);
        $this->purchase($taxed, $u['Bottle'], 10, 80, 100, now()->addYear()->toDateString(), [[$this->tax('Sales Tax', 17), 17]]);

        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($taxed, $u['Bottle'], 2, 100)]));
        $response = $this->post(route('invoices.return', $invoice->invoice_no))->assertRedirect();

        $this->assertEquals(234.0, round((float) $response->getSession()->get('print_return_payload')['totalReturn'], 2));
        $fresh = $invoice->fresh();
        $this->assertEquals(0.0, (float) $fresh->grand_total);
        $this->assertTrue((bool) $fresh->fully_returned);
    }

    public function test_fbr_failure_rolls_the_whole_sale_back(): void
    {
        DB::table('businesses')->where('id', $this->business->id)->update(['invoice_source' => 'fbr']);

        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 40)])
            ->assertStatus(502)->assertJsonPath('error', 'fbr_failed');

        $this->assertSame(0, Invoice::count());
        $this->assertEquals(28.0, $this->remaining($this->lotOld) + $this->remaining($this->lotSoon));
    }

    public function test_a_lot_is_not_sellable_on_its_expiry_date(): void
    {
        $today = $this->purchase($this->product, $this->unit['Box'], 1, 300, 560, now()->toDateString());

        $this->checkStock($this->product, 29, $this->unit['Tablet'])->assertJsonPath('status', 'partial');
        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 28, 40)]));
        $this->assertEquals(14.0, $this->remaining($today));
    }

    public function test_check_stock_offers_lots_in_the_requested_order(): void
    {
        $this->setBusinessPricing('stock-wise-price');

        $fefo = $this->checkStock($this->product, 15, $this->unit['Tablet'])->json('rows');
        $fifo = $this->checkStock($this->product, 15, $this->unit['Tablet'], [], ['lot_selection_mode' => 'fifo'])->json('rows');

        $this->assertSame($this->lotSoon->id, $fefo[0]['base_stock_sale_price_id']);
        $this->assertSame($this->lotOld->id, $fifo[0]['base_stock_sale_price_id']);
    }

    public function test_invoice_gets_a_local_number(): void
    {
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 1, 40)]));

        $this->assertMatchesRegularExpression('/^INV-\d{6,}$/', $invoice->invoice_no);
    }

    public function test_backfill_migration_repairs_businesses_without_preferences(): void
    {
        // Simulate the old state: one business with no rows, one with all price rows switched off.
        $bare = Business::create(['name' => 'Bare']);
        Preference::withoutGlobalScopes()->where('business_id', $bare->id)->forceDelete();
        Preference::withoutGlobalScopes()->where('business_id', $this->business->id)->where('type', 'sale_price')->update(['status' => false]);

        (require database_path('migrations/2026_11_04_100200_backfill_business_sale_preferences.php'))->up();

        foreach ([$bare->id, $this->business->id] as $businessId) {
            $active = Preference::withoutGlobalScopes()->where('business_id', $businessId)->where('status', true)->pluck('slug')->all();
            $this->assertContains('static-price', $active);
            $this->assertContains('fefo', $active);
            $this->assertContains('allow-price-change', $active);
            $this->assertSame(10, Preference::withoutGlobalScopes()->where('business_id', $businessId)->count());
        }
    }
}
