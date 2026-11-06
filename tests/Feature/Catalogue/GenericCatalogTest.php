<?php

namespace Tests\Feature\Catalogue;

use App\Models\Business;
use App\Models\GenericCategory;
use App\Models\GenericCompany;
use App\Models\GenericFarmula;
use App\Models\GenericProduct;
use App\Models\GenericProductParameter;
use App\Models\GenericProductType;
use App\Models\GenericStrength;
use App\Models\Product;
use App\Services\Catalogue\GenericImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.6a: generic catalogue import (G-1..G-6) and the catalogue page. */
class GenericCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    /** A catalogue entry: Box (sale 1000 / buy 800) → 10 Tablet (100 / 80). */
    private function generic(string $name, array $over = [], bool $withChain = true): GenericProduct
    {
        $g = GenericProduct::create($over + [
            'product_name' => $name, 'status' => 'approved',
            'generic_company_id' => GenericCompany::firstOrCreate(['name' => 'GSK'], ['status' => 'approved'])->id,
            'generic_product_type_id' => GenericProductType::firstOrCreate(['name' => 'Tablet'], ['status' => 'approved'])->id,
            'strength_id' => (string) GenericStrength::firstOrCreate(['name' => '500mg'], ['status' => 'approved'])->id,
            'farmula_id' => (string) GenericFarmula::firstOrCreate(['name' => 'Paracetamol'], ['status' => 'approved'])->id,
        ]);
        if ($withChain) {
            $box = GenericCategory::firstOrCreate(['name' => 'Box'], ['status' => 'approved']);
            $tab = GenericCategory::firstOrCreate(['name' => 'Tablet'], ['status' => 'approved']);
            GenericProductParameter::create(['generic_product_id' => $g->id, 'generic_category_id' => $box->id, 'parent_generic_category_id' => $box->id, 'child_generic_category_id' => $box->id, 'quantity' => 1, 'static_category_unit_purchase_price' => 800, 'static_category_unit_sale_price' => 1000]);
            GenericProductParameter::create(['generic_product_id' => $g->id, 'generic_category_id' => $box->id, 'parent_generic_category_id' => $box->id, 'child_generic_category_id' => $tab->id, 'quantity' => 10, 'static_category_unit_purchase_price' => 80, 'static_category_unit_sale_price' => 100]);
        }

        return $g;
    }

    public function test_import_creates_a_ready_product_with_its_packaging_in_this_business(): void
    {
        $g = $this->generic('Panadol');
        $this->post(route('generic_products.import'), ['product_id' => $g->id])->assertSessionHas('success');

        $p = Product::where('generic_product_id', $g->id)->firstOrFail();
        $this->assertSame($this->business->id, (int) $p->business_id);
        $this->assertFalse((bool) $p->is_draft);
        $this->assertSame(10.0, app(\App\Services\Sales\PackagingService::class)->baseUnitsPer($p->id, \App\Models\Category::where('name', 'Box')->value('id')));
        $this->assertSame('GSK', $p->company->name);
        // Bought through the normal purchase flow straight away.
        $this->purchase($p, \App\Models\Category::where('name', 'Box')->value('id'), 1, 800, 1000, now()->addYear()->toDateString());
    }

    public function test_import_rules_g1_g2_g3(): void
    {
        $pending = $this->generic('Pending one', ['status' => 'pending']);
        $this->post(route('generic_products.import'), ['product_id' => $pending->id])->assertSessionHas('error');       // G-2
        $this->assertSame(0, Product::count());

        $noChain = $this->generic('No packaging', [], withChain: false);
        $this->post(route('generic_products.import'), ['product_id' => $noChain->id])->assertSessionHas('success');
        $this->assertTrue((bool) Product::where('generic_product_id', $noChain->id)->value('is_draft'), 'G-1: no chain → draft');

        $bad = $this->generic('Bad prices');
        GenericProductParameter::where('generic_product_id', $bad->id)->update(['static_category_unit_sale_price' => 1]); // sale below purchase
        $this->post(route('generic_products.import'), ['product_id' => $bad->id]);
        $this->assertTrue((bool) Product::where('generic_product_id', $bad->id)->value('is_draft'), 'G-1: invalid chain → draft');

        // G-3: the business already has "Augmentin 500mg Tablet": linked, not copied.
        $type = \App\Models\ProductType::firstOrCreate(['name' => 'Tablet']); // earlier imports already made it
        $mine = Product::create(['product_name' => 'Augmentin', 'company_id' => \App\Models\Company::create(['name' => 'X'])->id, 'product_type_id' => $type->id,
            'strength_id' => (string) \App\Models\Strength::firstOrCreate(['name' => '500mg'])->id, 'is_draft' => true]);
        $g = $this->generic('Augmentin');
        $this->post(route('generic_products.import'), ['product_id' => $g->id])->assertSessionHas('success');
        $this->assertSame(1, Product::where('product_name', 'Augmentin')->count());
        $this->assertSame($g->id, (int) $mine->fresh()->generic_product_id);
        $this->post(route('generic_products.import'), ['product_id' => $g->id])->assertSessionHas('error'); // already in the list
    }

    public function test_bulk_import_isolates_failures_and_the_sweep_releases_stuck_batches(): void
    {
        $a = $this->generic('A');
        $b = $this->generic('B');
        $this->post(route('generic_products.bulkImport'), ['product_ids' => [$a->id, $b->id, 999999]])->assertSessionHas('success');
        // The queue runs synchronously in tests: processed right away.
        $batch = DB::table('generic_product_import_batches')->latest('id')->first();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, (int) $batch->imported_count);
        $this->assertSame(2, Product::count());

        $this->post(route('generic_products.importAll'))->assertSessionHas('error'); // nothing new left

        // G-4: a batch stuck in "processing" (worker died) is released and finished by the sweep.
        $c = $this->generic('C');
        $stuck = DB::table('generic_product_import_batches')->insertGetId(['business_id' => $this->business->id, 'product_ids' => json_encode([$c->id]),
            'status' => 'processing', 'started_at' => now()->subHours(2), 'created_at' => now(), 'updated_at' => now()]);
        session()->forget(['business_id']);
        app(GenericImportService::class)->sweep();
        $this->assertSame('completed', DB::table('generic_product_import_batches')->where('id', $stuck)->value('status'));
        $this->assertSame($this->business->id, (int) Product::withoutGlobalScopes()->where('generic_product_id', $c->id)->value('business_id'));
    }

    public function test_g5_archived_entries_hide_and_g6_local_products_do_not_change_the_shared_catalogue(): void
    {
        $gone = $this->generic('Gone');
        $gone->delete();
        $this->get(route('generic_products.index'))->assertInertia(fn (Assert $a) => $a->component('Generic/Index')->where('rows.total', 0));
        $this->post(route('generic_products.import'), ['product_id' => $gone->id])->assertSessionHas('error');

        // G-6: importing then editing the price locally doesn't touch the approved catalogue entry.
        $g = $this->generic('Shared');
        $this->post(route('generic_products.import'), ['product_id' => $g->id]);
        $p = Product::where('generic_product_id', $g->id)->firstOrFail();
        $this->post(route('products.setup.save', $p->id), ['levels' => [
            ['category_id' => \App\Models\Category::where('name', 'Box')->value('id'), 'purchase_price' => 1, 'sale_price' => 2],
            ['category_id' => \App\Models\Category::where('name', 'Tablet')->value('id'), 'quantity' => 10],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(1000.0, (float) GenericProductParameter::where('generic_product_id', $g->id)->where('child_generic_category_id', GenericCategory::where('name', 'Box')->value('id'))->value('static_category_unit_sale_price'));

        // A business's own new product is only a pending suggestion, invisible to other businesses.
        [$own] = $this->makeProduct('Secret blend', ['Bottle' => 1]);
        \App\Services\GenericProductService::syncProductToGeneric($own->fresh());
        $suggestion = GenericProduct::where('product_name', 'Secret blend')->firstOrFail();
        $this->assertSame('pending', $suggestion->status);
        $this->assertSame($this->business->id, (int) $suggestion->suggested_by_business_id);
    }

    public function test_page_filters_search_and_details(): void
    {
        $this->generic('Alpha');
        $b = $this->generic('Beta', ['farmula_id' => (string) GenericFarmula::create(['name' => 'Ibuprofen', 'status' => 'approved'])->id]);
        $this->post(route('generic_products.import'), ['product_id' => $b->id]);

        $this->get(route('generic_products.index'))->assertInertia(fn (Assert $a) => $a->where('stats.total', 2)->where('stats.available', 1)->where('stats.mine', 1)->where('canImport', true));
        $this->get(route('generic_products.index', ['status' => 'available']))->assertInertia(fn (Assert $a) => $a->where('rows.total', 1)->where('rows.data.0.name', 'Alpha'));
        $this->get(route('generic_products.index', ['q' => 'ibupro']))->assertInertia(fn (Assert $a) => $a->where('rows.total', 1)->where('rows.data.0.product_id', fn ($v) => $v !== null));
        $this->getJson(route('generic_products.details', $b->id))->assertOk()->assertJsonPath('chain.1.unit', 'Tablet')->assertJsonPath('chain.1.quantity', 10)->assertJsonPath('formulas.0', 'Ibuprofen');

        $pending = $this->generic('Hidden', ['status' => 'pending', 'suggested_by_business_id' => Business::create(['name' => 'Other'])->id]);
        $this->getJson(route('generic_products.details', $pending->id))->assertNotFound();
    }
}
