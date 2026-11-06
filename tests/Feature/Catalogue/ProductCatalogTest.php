<?php

namespace Tests\Feature\Catalogue;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductParameter;
use App\Services\Sales\PackagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.4a/5.4c: product list with real lot stock, product page, setup wizard. */
class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    private function draft(string $name, array $extra = []): Product
    {
        $company = \App\Models\Company::firstOrCreate(['name' => 'ACME Pharma']);
        $type = \App\Models\ProductType::firstOrCreate(['name' => 'Tablet']);

        return Product::create(['product_name' => $name, 'is_draft' => true, 'company_id' => $company->id, 'product_type_id' => $type->id] + $extra);
    }

    private function unit(string $name): Category
    {
        return Category::firstOrCreate(['name' => $name]);
    }

    public function test_list_uses_real_lot_stock_for_every_filter(): void
    {
        [$inStock, $u1] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($inStock, $u1['Box'], 2, 50, 80, now()->addDays(40)->toDateString());          // 20 tablets, expiring soon
        [$expired, $u2] = $this->makeProduct('Bravo', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($expired, $u2['Box'], 1, 50, 80, now()->addDay()->toDateString());
        $this->travel(3)->days();                                                                       // Bravo's only lot is now expired
        $this->makeProduct('Charlie');                                                                  // never purchased
        $draft = $this->draft('Delta draft');

        $this->get(route('products.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Products/Index')
            ->where('stats.total', 3)->where('stats.drafts', 1)->where('stats.in_stock', 1)->where('stats.out', 2)
            ->where('stats.expired', 1)->where('stats.expiring', 1));

        $this->get(route('products.index', ['status' => 'in_stock']))->assertInertia(fn (Assert $page) => $page
            ->where('rows.total', 1)->where('rows.data.0.name', 'Alpha')->where('rows.data.0.stock', 20)->where('rows.data.0.unit', 'Tablet'));
        $this->get(route('products.index', ['status' => 'expired']))->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.name', 'Bravo')->where('rows.data.0.stock', 0)->where('rows.data.0.expired_stock', 10));
        $this->get(route('products.index', ['status' => 'drafts']))->assertInertia(fn (Assert $page) => $page->where('rows.data.0.name', 'Delta draft')->where('rows.data.0.draft', true));
        $this->get(route('products.index', ['q' => 'ACME']))->assertInertia(fn (Assert $page) => $page->where('rows.total', 3));

        // Old URLs land on the right filter.
        $this->get(route('outstock'))->assertRedirect(route('products.index', ['status' => 'out']));
        $this->get(route('expired'))->assertRedirect(route('products.index', ['status' => 'expired']));
        $this->get(route('products.drafts'))->assertRedirect(route('products.index', ['status' => 'drafts']));
        $this->get(route('products.details', $draft->id))->assertRedirect(route('products.show', $draft->id));
    }

    public function test_product_page_shows_lots_chain_and_lock(): void
    {
        [$product, $unit] = $this->makeProduct('Panadol', ['Box' => 1, 'Strip' => 2, 'Tablet' => 10], ['Box' => [100, 70]]);
        $this->purchase($product, $unit['Box'], 3, 70, 100, now()->addYear()->toDateString(), batch: 'P-1');

        $this->get(route('products.show', $product->id))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Products/Show')
            ->where('product.stock', 60)->where('product.unit', 'Tablet')
            ->has('chain', 3)->where('chain.0.name', 'Box')->where('chain.0.base_units', 20)->where('chain.0.sale_price', 100)
            ->where('lots.0.batch', 'P-1')->where('lots.0.remaining', 60)
            ->where('locked', 'This product already has purchases.'));
    }

    public function test_setup_wizard_activates_a_draft_and_the_engine_reads_the_chain(): void
    {
        $draft = $this->draft('Amoxil');
        $box = $this->unit('Box');
        $strip = $this->unit('Strip');
        $cap = $this->unit('Capsule');

        $this->get(route('products.setup-wizard', $draft->id))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Products/Setup')->where('chain', [])->where('locked', null));

        $this->post(route('products.setup.save', $draft->id), ['levels' => [
            ['category_id' => $box->id, 'purchase_price' => 300, 'sale_price' => 400],
            ['category_id' => $strip->id, 'quantity' => 3],
            ['category_id' => $cap->id, 'quantity' => 8, 'purchase_price' => 12.5, 'sale_price' => 17],
        ]])->assertRedirect(route('products.show', $draft->id))->assertSessionHas('success');

        $this->assertFalse((bool) $draft->fresh()->is_draft);
        $packaging = app(PackagingService::class);
        $packaging->forget();
        $this->assertSame($cap->id, $packaging->baseCategoryId($draft->id));
        $this->assertEquals(24, $packaging->baseUnitsPer($draft->id, $box->id));
        $this->assertSame(2, ProductCategory::where('product_id', $draft->id)->count());
    }

    public function test_setup_validation(): void
    {
        $p = $this->draft('Bad');
        $box = $this->unit('Box');
        $tab = $this->unit('Tablet');
        $save = fn (array $levels) => $this->post(route('products.setup.save', $p->id), ['levels' => $levels]);

        $save([])->assertSessionHasErrors('levels');
        $save([['category_id' => $box->id], ['category_id' => $box->id, 'quantity' => 2]])->assertSessionHasErrors('levels');
        $save([['category_id' => $box->id], ['category_id' => $tab->id, 'quantity' => 0]])->assertSessionHasErrors('levels.1.quantity');
        $save([['category_id' => $box->id], ['category_id' => $tab->id, 'quantity' => -5]])->assertSessionHasErrors('levels.1.quantity');
        $save([['category_id' => $box->id, 'purchase_price' => 50, 'sale_price' => 40]])->assertSessionHasErrors('levels.0.sale_price');
        $save([['category_id' => 999999]])->assertSessionHasErrors('levels.0.category_id');
        $this->assertTrue((bool) $p->fresh()->is_draft);
        $this->assertSame(0, ProductParameter::where('product_id', $p->id)->count());
    }

    public function test_packaging_is_locked_once_stock_exists_but_prices_can_change(): void
    {
        [$product, $unit] = $this->makeProduct('Locked', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($product, $unit['Box'], 1, 50, 80, now()->addYear()->toDateString());
        $strip = $this->unit('Strip');

        $this->post(route('products.setup.save', $product->id), ['levels' => [
            ['category_id' => $unit['Box']], ['category_id' => $unit['Tablet'], 'quantity' => 20],
        ]])->assertSessionHasErrors('levels');
        $this->post(route('products.setup.save', $product->id), ['levels' => [
            ['category_id' => $unit['Box']], ['category_id' => $strip->id, 'quantity' => 2], ['category_id' => $unit['Tablet'], 'quantity' => 5],
        ]])->assertSessionHasErrors('levels');

        $this->post(route('products.setup.save', $product->id), ['levels' => [
            ['category_id' => $unit['Box'], 'sale_price' => 90, 'purchase_price' => 60], ['category_id' => $unit['Tablet'], 'quantity' => 10, 'sale_price' => 9.5],
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals(90, ProductParameter::where('product_id', $product->id)->where('child_category_id', $unit['Box'])->value('static_category_unit_sale_price'));
        $this->assertEquals(10, app(PackagingService::class)->baseUnitsPer($product->id, $unit['Box']));
    }

    public function test_other_businesses_products_and_units_are_off_limits(): void
    {
        $other = Business::create(['name' => 'Other']);
        $foreign = Product::withoutGlobalScopes()->forceCreate(['product_name' => 'Foreign', 'is_draft' => true, 'business_id' => $other->id, 'company_id' => \App\Models\Company::firstOrCreate(['name' => 'ACME Pharma'])->id, 'product_type_id' => \App\Models\ProductType::firstOrCreate(['name' => 'Tablet'])->id]);
        $foreignUnit = Category::withoutGlobalScopes()->forceCreate(['name' => 'Crate', 'business_id' => $other->id]);
        $mine = $this->draft('Mine');

        $this->get(route('products.show', $foreign->id))->assertNotFound();
        $this->get(route('products.setup-wizard', $foreign->id))->assertNotFound();
        $this->post(route('products.setup.save', $foreign->id), ['levels' => [['category_id' => $this->unit('Box')->id]]])->assertNotFound();
        $this->post(route('products.setup.save', $mine->id), ['levels' => [['category_id' => $foreignUnit->id]]])->assertSessionHasErrors('levels.0.category_id');
        $this->assertSame(0, ProductParameter::withoutGlobalScopes()->where('product_id', $foreign->id)->count());
    }

    public function test_adding_a_product_goes_to_its_setup_and_old_packaging_urls_redirect(): void
    {
        $company = \App\Models\Company::create(['name' => 'Getz']);
        $type = \App\Models\ProductType::create(['name' => 'Syrup']);
        $this->post(route('products.store'), ['product_name' => 'Brufen syrup', 'company_id' => $company->id, 'product_type_id' => $type->id])
            ->assertRedirect(route('products.setup-wizard', Product::where('product_name', 'Brufen syrup')->value('id')));

        $id = Product::where('product_name', 'Brufen syrup')->value('id');
        $this->get(route('products.parameters', $id))->assertRedirect(route('products.setup-wizard', $id));
        $this->get(route('product-categories.create', ['product_id' => $id]))->assertRedirect(route('products.setup-wizard', $id));
        $this->post('/admin/product-categories', ['product_id' => $id])->assertStatus(405);
    }
}
