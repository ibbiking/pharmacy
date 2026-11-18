<?php

namespace Tests\Feature\Sales;

use App\Models\BaseStockSalePrice;
use App\Services\Sales\SalePreferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Which batch is sold: FEFO / FIFO / any batch (business-wide, item override) and the batch the cashier picks at the POS. */
class BatchSelectionTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    /** @return array{0: \App\Models\Product, 1: array, 2: BaseStockSalePrice, 3: BaseStockSalePrice, 4: BaseStockSalePrice} */
    private function threeBatches(): array
    {
        [$p, $u] = $this->makeProduct('Panadol', ['Piece' => 1], ['Piece' => [50, 40]]);
        $old = $this->purchase($p, $u['Piece'], 10, 40, 50, now()->addYears(3)->toDateString(), batch: 'OLD-1');   // bought first, expires last
        $soon = $this->purchase($p, $u['Piece'], 20, 40, 50, now()->addMonths(2)->toDateString(), batch: 'SOON-2'); // expires first
        $big = $this->purchase($p, $u['Piece'], 90, 40, 50, now()->addYear()->toDateString(), batch: 'BIG-3');      // most stock

        return [$p, $u, $old, $soon, $big];
    }

    public function test_any_batch_mode_sells_the_batch_with_most_stock_and_never_an_expired_one(): void
    {
        [$p, $u, $old, $soon, $big] = $this->threeBatches();
        $expired = $this->purchase($p, $u['Piece'], 500, 40, 50, now()->addYear()->toDateString(), batch: 'EXP-4');
        $expired->forceFill(['expiry_date' => now()->subDay()->toDateString()])->save();
        $this->setBusinessLotMode('random');

        $this->saveInvoice([$this->line($p, $u['Piece'], 5, 50)])->assertOk();

        $this->assertEquals(85, $this->remaining($big));
        $this->assertEquals(10, $this->remaining($old));
        $this->assertEquals(20, $this->remaining($soon));
        $this->assertEquals(500, $this->remaining($expired));
    }

    public function test_business_wide_rule_applies_and_the_item_setting_overrides_it(): void
    {
        [$p] = $this->threeBatches();
        $this->setBusinessLotMode('random');
        $this->assertSame('random', app(SalePreferenceResolver::class)->forProduct($p->id)->lotMode);

        $p->forceFill(['lot_selection_mode' => 'fifo'])->save();
        $this->forgetResolvedSettings();
        $this->assertSame('fifo', app(SalePreferenceResolver::class)->forProduct($p->id)->lotMode);

        // Saving the business-wide choice through the settings screen.
        $this->forgetResolvedSettings();
        $settings = app(\App\Services\Catalogue\SalePreferenceService::class)->business($this->business->id);
        $this->assertSame('random', $settings['lot_mode']);
    }

    /** FEFO would take SOON-2, but the customer brought a pack of OLD-1: the cashier picks it and stock comes off OLD-1. */
    public function test_the_batch_picked_at_the_pos_is_sold_first_whatever_the_rule(): void
    {
        [$p, $u, $old, $soon] = $this->threeBatches();
        $this->setBusinessLotMode('fefo');

        $quote = $this->postJson(route('pos.quote'), ['lines' => [[
            'product_id' => $p->id, 'category_id' => $u['Piece'], 'qty' => 3, 'price' => null, 'base_stock_sale_price_id' => $old->id,
        ]]])->assertOk()->json('lines.0');
        $this->assertSame($old->id, $quote['lots'][0]['id']);
        $this->assertSame('fefo', $quote['rule_lot_mode']);

        $this->saveInvoice([$this->line($p, $u['Piece'], 3, 50, ['base_stock_sale_price_id' => $old->id])])->assertOk();
        $this->assertEquals(7, $this->remaining($old));
        $this->assertEquals(20, $this->remaining($soon));

        // Too little in the picked batch: the rest comes from the next batch by the rule.
        $this->saveInvoice([$this->line($p, $u['Piece'], 12, 50, ['base_stock_sale_price_id' => $old->id])])->assertOk();
        $this->assertEquals(0, $this->remaining($old));
        $this->assertEquals(15, $this->remaining($soon));
    }

    public function test_batch_picker_lists_sellable_batches_in_rule_order_and_searches_batch_numbers(): void
    {
        [$p, , $old, $soon, $big] = $this->threeBatches();
        $this->setBusinessLotMode('fefo');

        $rows = $this->getJson(route('products.pos.batches', $p->id))->assertOk()->json('data');
        $this->assertSame([$soon->id, $big->id, $old->id], array_column($rows, 'id'));
        $this->assertStringContainsString('SOON-2', $rows[0]['label']);

        $hit = $this->getJson(route('products.pos.batches', ['id' => $p->id, 'q' => 'big']))->assertOk()->json('data');
        $this->assertSame([$big->id], array_column($hit, 'id'));
    }
}
