<?php

namespace Tests\Feature\Stock;

use App\Models\Business;
use App\Models\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.5b: purchases list (stat strip filters, search) and the purchase page (remaining, sales from the batch). */
class PurchasePagesTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_list_filters_and_search(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($p, $u['Box'], 1, 100, 150, now()->addDays(30)->toDateString(), batch: 'SOON');
        $this->purchase($p, $u['Box'], 1, 100, 150, now()->subDay()->toDateString(), batch: 'OLD');
        $keep = $this->purchase($p, $u['Box'], 2, 100, 150, now()->addYears(2)->toDateString(), batch: 'LONG');
        $gone = $this->purchase($p, $u['Box'], 1, 100, 150, now()->addYears(2)->toDateString(), batch: 'GONE');
        $this->delete(route('purchases.destroy', $gone->purchase_id));

        $this->get(route('purchases.index'))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Purchases/Index')
            ->where('rows.total', 3)->where('stats.total', 3)->where('stats.month', 3)->where('stats.expiring', 1)->where('stats.expired', 1)->where('stats.archived', 1));
        $this->get(route('purchases.index', ['status' => 'expiring']))->assertInertia(fn (Assert $a) => $a->where('rows.total', 1)->where('rows.data.0.batch', 'SOON'));
        $this->get(route('purchases.index', ['status' => 'expired']))->assertInertia(fn (Assert $a) => $a->where('rows.data.0.batch', 'OLD'));
        $this->get(route('purchases.index', ['status' => 'archived']))->assertInertia(fn (Assert $a) => $a->where('rows.total', 1)->where('rows.data.0.archived', true));
        $this->get(route('purchases.index', ['q' => 'LONG']))->assertInertia(fn (Assert $a) => $a->where('rows.total', 1)
            ->where('rows.data.0.remaining', 20)->where('rows.data.0.base_unit', 'Tablet'));
        $this->get(route('purchases.index', ['q' => 'Main Supplier', 'sort' => 'expiry_date', 'dir' => 'asc']))->assertInertia(fn (Assert $a) => $a->where('rows.total', 3)->where('rows.data.0.batch', 'OLD'));
        $this->assertNotNull($keep);
    }

    public function test_purchase_page_shows_what_was_sold_from_the_batch(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);
        $st = $this->tax('Sales Tax', 10);
        $lot = $this->purchase($p, $u['Box'], 2, 100, 150, now()->addYear()->toDateString(), [[$st, 10]], extraPaidPercent: 5);
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 4, 15)]));

        $this->get(route('purchases.show', $lot->purchase_id))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Purchases/Show')
            ->where('purchase.sold', 4)->where('purchase.remaining', 16)->where('purchase.extra_percent', 5)
            ->where('purchase.sale_taxes.0.name', 'Sales Tax')->where('sales.total', 1)->where('sales.data.0.invoice_no', $invoice->invoice_no)
            ->where('archiveBlocker', fn ($v) => is_string($v)));
    }

    public function test_other_businesses_purchases_are_not_found_and_viewers_cannot_change(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1]);
        $lot = $this->purchase($p, $u['Box'], 1, 10, 20, now()->addYear()->toDateString());
        $foreign = Purchase::findOrFail($lot->purchase_id);
        $foreign->forceFill(['business_id' => Business::create(['name' => 'Other'])->id])->saveQuietly(); // now belongs to another business
        $this->get(route('purchases.show', $foreign->id))->assertNotFound();
        $this->delete(route('purchases.destroy', $foreign->id))->assertNotFound();
    }

    public function test_the_one_form_serves_new_bulk_quick_stock_and_edit(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10], ['Box' => [1000, 800], 'Tablet' => [100, 80]]); // [sale, purchase]
        $draft = \App\Models\Product::create(['product_name' => 'Draft', 'is_draft' => true, 'company_id' => $p->company_id, 'product_type_id' => $p->product_type_id]);

        $this->get(route('purchases.create', ['product_id' => $p->id]))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Purchases/Form')
            ->where('purchase', null)->where('preset.value', $p->id));
        $this->get(route('purchases.create', ['product_id' => $draft->id]))->assertInertia(fn (Assert $a) => $a->where('preset', null));
        $this->get(route('purchases.create-multiple', ['product_id' => $p->id]))->assertRedirect(route('purchases.create', ['product_id' => $p->id]));
        $this->get(route('products.quick-stock', $p->id))->assertRedirect(route('purchases.create', ['product_id' => $p->id]));

        // Units with base counts and suggested prices (static prices here).
        $this->getJson(route('purchases.product-info', $p->id))->assertOk()
            ->assertJsonPath('base_unit', 'Tablet')->assertJsonPath('units.0.label', 'Box')->assertJsonPath('units.0.base_units', 10)
            ->assertJsonPath('units.0.unit_sale_price', 1000);
        $this->getJson(route('purchases.product-info', $draft->id))->assertNotFound();
        $this->getJson(route('lookup', ['entity' => 'stock-products']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Alpha');

        // Edit shows the lock once something was sold.
        $lot = $this->purchase($p, $u['Box'], 2, 800, 1000, now()->addYear()->toDateString());
        $this->get(route('purchases.edit', $lot->purchase_id))->assertInertia(fn (Assert $a) => $a->component('Purchases/Form')
            ->where('purchase.locked', false)->where('purchase.line.category_id', $u['Box']));
        $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 5, 100)]));
        $this->get(route('purchases.edit', $lot->purchase_id))->assertInertia(fn (Assert $a) => $a->where('purchase.locked', true)->where('purchase.sold', 0.5));
    }
}
