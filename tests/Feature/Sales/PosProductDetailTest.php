<?php

namespace Tests\Feature\Sales;

use App\Models\BaseStockSalePrice;
use App\Models\Inventory\Warehouse;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** POS search, hover-detail bar, stock and price summaries (restored from the Blade POS / products list). */
class PosProductDetailTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    /** Goods without an expiry date (stationery, hardware) never expire: the POS must sell them. */
    public function test_stock_without_an_expiry_date_is_sellable_in_search_and_summaries(): void
    {
        [$p, $u] = $this->makeProduct('Spiral Notebook', ['Pack' => 1, 'Piece' => 10], ['Pack' => [1800, 1200], 'Piece' => [180, 120]]);
        $lot = $this->purchase($p, $u['Pack'], 6, 1200, 1800, now()->addYear()->toDateString());
        $lot->forceFill(['expiry_date' => null])->save();

        $hit = collect($this->getJson(route('products.search', ['q' => 'Spiral']))->assertOk()->json())->firstWhere('id', $p->id);
        $this->assertNotNull($hit);
        $this->assertArrayNotHasKey('out_of_stock', $hit, 'a lot without expiry must not read as out of stock');
        $this->assertEquals(60, $hit['available_base_stock']);

        $summary = $this->getJson(route('products.stock-summary', $p->id))->assertOk()->json('summary');
        $this->assertNotEmpty($summary['available']);
        $this->assertSame([], $summary['expired']);

        // And checkout agrees: it sells from the same lot.
        $this->saveInvoice([$this->line($p, $u['Piece'], 15, 180)])->assertOk();
        $this->assertEquals(45, BaseStockSalePrice::find($lot->id)->remaining_base_stock);
    }

    public function test_detail_bar_shows_price_mode_rules_discount_and_stock_of_this_till(): void
    {
        [$p, $u] = $this->makeProduct('Panadol', ['Box' => 1, 'Strip' => 10], ['Box' => [500, 400], 'Strip' => [50, 40]]);
        $p->forceFill(['discount_percent' => 5, 'lock_max_discount' => true])->save();
        $this->purchase($p, $u['Box'], 2, 400, 500, now()->addYear()->toDateString());
        $this->setPosRule('max-line-discount-percent', true, 10);

        $d = $this->getJson(route('products.pos.availability', $p->id))->assertOk()->json();
        $this->assertSame('Panadol', $d['product_name']);
        $this->assertNotEmpty($d['preference']['name']);
        $this->assertContains($d['preference']['source'], ['product', 'global', 'default']);
        $this->assertIsBool($d['preference']['including_tax']);
        $this->assertContains($d['preference']['lot_mode'], ['fefo', 'fifo']);
        $this->assertEquals(10, $d['preference']['max_line_discount_percent']);
        $this->assertEquals(5, $d['discount']['percent']);
        $this->assertTrue($d['discount']['locked']);
        $this->assertNotEmpty($d['summary']['available']);
        $this->assertNull($d['warehouse'], 'no Inventory Pro: every warehouse');

        $price = $this->getJson(route('products.price-summary', $p->id))->assertOk()->json();
        $this->assertNotEmpty($price['active_preference']);
        $this->assertContains($price['tax_included'], ['Yes', 'No']);
        $this->assertCount(2, $price['summary']);
    }

    /** Inventory Pro: search and the detail bar count only what the POS warehouse can sell; other warehouses are listed apart. */
    public function test_search_and_detail_use_the_pos_warehouse_like_checkout(): void
    {
        app(ModuleManager::class)->activate($this->business, 'inventory_pro');
        app(ModuleManager::class)->forget();
        [$p, $u] = $this->makeProduct('Stapler', ['Piece' => 1], ['Piece' => [300, 200]]);
        $this->purchase($p, $u['Piece'], 10, 200, 300, now()->addYear()->toDateString());
        $main = Warehouse::firstOrFail();
        $this->post(route('warehouses.store'), ['name' => 'Branch store'])->assertSessionHasNoErrors();
        $branch = Warehouse::where('name', 'Branch store')->firstOrFail();
        $this->post(route('transfers.store'), ['from_warehouse_id' => $main->id, 'to_warehouse_id' => $branch->id, 'date' => today()->toDateString(),
            'lines' => [['product_id' => $p->id, 'category_id' => $u['Piece'], 'quantity' => 3]]])->assertSessionHasNoErrors();
        $this->post(route('warehouses.pos'), ['warehouse_id' => $branch->id])->assertSessionHasNoErrors();

        $hit = collect($this->getJson(route('products.search', ['q' => 'Stapler']))->json())->firstWhere('id', $p->id);
        $this->assertEquals(3, $hit['available_base_stock'], 'only the POS warehouse, as checkout');

        $d = $this->getJson(route('products.pos.availability', $p->id))->assertOk()->json();
        $this->assertSame('Branch store', $d['warehouse']);
        $this->assertEquals(3, (float) $d['summary']['available'][0]['quantity']);
        $this->assertSame([$main->name], array_column($d['other_warehouses'], 'name'));

        $s = $this->getJson(route('products.stock-summary', $p->id))->assertOk()->json();
        $this->assertEquals(10, (float) $s['summary']['available'][0]['quantity'], 'summary: all warehouses');
        $this->assertSame('Branch store', $s['warehouses'][0]['name']);
        $this->assertTrue($s['warehouses'][0]['is_pos']);
    }
}
