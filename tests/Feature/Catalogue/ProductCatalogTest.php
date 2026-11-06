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

/** Step 5.4a/b/c: product list with real lot stock, product page, add/edit form, archive/restore, setup wizard. */
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
        $this->post(route('products.store'), ['product_name' => 'Brufen syrup', 'company' => ['id' => $company->id], 'product_type' => ['id' => $type->id]])
            ->assertRedirect(route('products.setup-wizard', Product::where('product_name', 'Brufen syrup')->value('id')));

        $id = Product::where('product_name', 'Brufen syrup')->value('id');
        $this->get(route('products.parameters', $id))->assertRedirect(route('products.setup-wizard', $id));
        $this->get(route('product-categories.create', ['product_id' => $id]))->assertRedirect(route('products.setup-wizard', $id));
        $this->post('/admin/product-categories', ['product_id' => $id])->assertStatus(405);
    }

    public function test_add_form_renders_and_creates_typed_master_data_in_this_business(): void
    {
        $this->get(route('products.create'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Products/Form')->where('product', null));
        $strength = \App\Models\Strength::create(['name' => '250mg']);

        $this->post(route('products.store'), [
            'product_name' => '  Amoxil  ',
            'company' => ['name' => 'GSK'],
            'product_type' => ['name' => 'Capsule'],
            'strengths' => [['id' => $strength->id], ['name' => '500']], // a numeric NAME is not an id
            'formulas' => [['name' => 'Amoxicillin']],
            'barcode' => '8964000111',
            'lock_max_discount' => false, 'discount_percent' => 30,
        ])->assertSessionHasNoErrors();

        $p = Product::where('product_name', 'Amoxil')->firstOrFail();
        $this->assertTrue((bool) $p->is_draft);
        $this->assertSame('GSK', $p->company->name);
        $this->assertSame([$strength->id, \App\Models\Strength::where('name', '500')->value('id')], array_map('intval', explode(',', $p->strength_id)));
        $this->assertSame(0.0, (float) $p->discount_percent, 'no cap is stored unless the lock is on');
        $this->assertSame($this->business->id, \App\Models\Company::where('name', 'GSK')->value('business_id'));

        // Typing an existing name reuses it instead of creating a duplicate.
        $this->post(route('products.store'), ['product_name' => 'Augmentin', 'company' => ['name' => 'GSK'], 'product_type' => ['name' => 'Capsule']])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\Company::where('name', 'GSK')->count());
    }

    public function test_form_rules_are_checked_on_the_server(): void
    {
        $s1 = \App\Models\Strength::create(['name' => '5mg']);
        $s2 = \App\Models\Strength::create(['name' => '10mg']);
        $first = $this->draft('Cardio', ['strength_id' => "{$s1->id},{$s2->id}", 'barcode' => 'B-1']);
        $ref = ['company' => ['id' => $first->company_id], 'product_type' => ['id' => $first->product_type_id]];

        $this->post(route('products.store'), ['product_name' => ''] + $ref)->assertSessionHasErrors('product_name');
        $this->post(route('products.store'), ['product_name' => 'X'])->assertSessionHasErrors(['company', 'product_type']);
        // Same name + type + strengths (in any order) clashes; a different strength set does not.
        $this->post(route('products.store'), ['product_name' => 'Cardio', 'strengths' => [['id' => $s2->id], ['id' => $s1->id]]] + $ref)->assertSessionHasErrors('product_name');
        $this->post(route('products.store'), ['product_name' => 'Cardio', 'strengths' => [['id' => $s1->id]]] + $ref)->assertSessionHasNoErrors();
        $this->post(route('products.store'), ['product_name' => 'Other', 'barcode' => 'B-1'] + $ref)->assertSessionHasErrors('barcode');
        $this->post(route('products.store'), ['product_name' => 'Other', 'lock_max_discount' => true, 'discount_percent' => 120] + $ref)->assertSessionHasErrors('discount_percent');

        // Another business's entries can't be referenced by id.
        $foreign = \App\Models\Company::withoutGlobalScopes()->forceCreate(['name' => 'Foreign Co', 'business_id' => Business::create(['name' => 'Other'])->id]);
        $this->post(route('products.store'), ['product_name' => 'Y', 'company' => ['id' => $foreign->id], 'product_type' => $ref['product_type']])->assertSessionHasErrors('company');

        // Editing keeps its own name/barcode and saves the discount cap.
        $this->get(route('products.edit', $first->id))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Products/Form')
            ->where('product.strengths.0.label', '5mg')->where('product.strengths.1.label', '10mg'));
        $this->put(route('products.update', $first->id), ['product_name' => 'Cardio', 'barcode' => 'B-1', 'strengths' => [['id' => $s1->id], ['id' => $s2->id]],
            'lock_max_discount' => true, 'discount_percent' => 10] + $ref)->assertRedirect(route('products.show', $first->id));
        $this->assertSame(10.0, (float) $first->fresh()->discount_percent);
    }

    public function test_archive_is_blocked_while_stock_remains_and_restore_checks_duplicates(): void
    {
        [$stocked, $u] = $this->makeProduct('Stocked', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($stocked, $u['Box'], 1, 50, 80, now()->addYear()->toDateString());
        $this->delete(route('products.destroy', $stocked->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($stocked);

        $old = $this->draft('Twin');
        $this->delete(route('products.destroy', $old->id))->assertRedirect(route('products.index'));
        $this->assertSoftDeleted($old);
        $this->get(route('products.index', ['status' => 'archived']))->assertInertia(fn (Assert $p) => $p->where('rows.total', 1)->where('rows.data.0.archived', true)->where('stats.archived', 1));
        $this->get(route('products.show', $old->id))->assertOk()->assertInertia(fn (Assert $p) => $p->where('product.archived', true));

        $this->draft('Twin'); // a new active one with the same name/type
        $this->post(route('products.restore', $old->id))->assertSessionHas('error');
        $this->assertSoftDeleted($old);
    }
}
