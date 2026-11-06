<?php

namespace Tests\Feature\Catalogue;

use App\Models\Business;
use App\Models\GenericCompany;
use App\Models\GenericProduct;
use App\Models\GenericProductParameter;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.6b: suggest / add / edit catalogue entries and review them; G-7..G-9. */
class GenericEntryTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
        $this->admin = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('x')]);
        $this->admin->assignRole(Role::findOrCreate('super-admin'));
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['business_id' => $this->business->id]);
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession(['business_id' => $this->business->id]);
    }

    private function payload(array $over = []): array
    {
        return $over + [
            'product_name' => 'Calpol', 'company' => ['name' => 'GSK'], 'type' => ['name' => 'Syrup'],
            'strengths' => [['name' => '120mg/5ml']], 'formulas' => [['name' => 'Paracetamol']],
            'levels' => [
                ['unit' => ['name' => 'Carton'], 'purchase_price' => 1000, 'sale_price' => 1200],
                ['unit' => ['name' => 'Bottle'], 'quantity' => 10, 'purchase_price' => 100, 'sale_price' => 120],
            ],
        ];
    }

    public function test_a_business_suggests_and_edits_only_while_pending(): void
    {
        $this->asOwner()->get(route('generic_products.suggest'))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Generic/Edit')->where('admin', false));
        $this->asOwner()->post(route('generic_products.suggest.store'), $this->payload())->assertSessionHasNoErrors();

        $g = GenericProduct::where('product_name', 'Calpol')->firstOrFail();
        $this->assertSame('pending', $g->status);
        $this->assertSame($this->business->id, (int) $g->suggested_by_business_id);
        $this->assertSame('pending', GenericCompany::where('name', 'GSK')->value('status'), 'typed names wait for review too');
        $this->assertSame(2, GenericProductParameter::where('generic_product_id', $g->id)->count());

        // Hidden from other businesses (lookups and catalogue), visible to the suggester.
        $this->asOwner()->getJson(route('lookup', ['entity' => 'generic-companies']))->assertJsonPath('data.0.label', 'GSK');
        $other = Business::create(['name' => 'Other']);
        $otherOwner = User::create(['name' => 'O', 'email' => 'o@example.test', 'password' => bcrypt('x')]);
        $otherOwner->businesses()->attach($other->id, ['role' => 'owner']);
        $this->actingAs($otherOwner)->withSession(['business_id' => $other->id])->getJson(route('lookup', ['entity' => 'generic-companies']))->assertJsonCount(0, 'data');
        $this->actingAs($otherOwner)->withSession(['business_id' => $other->id])->get(route('generic_products.edit', $g->id))->assertForbidden();

        $this->asOwner()->put(route('generic_products.update', $g->id), $this->payload(['product_name' => 'Calpol Plus']))->assertSessionHasNoErrors();
        $this->asAdmin()->post(route('generic_products.review'), ['action' => 'approve', 'ids' => [$g->id]])->assertSessionHas('success');
        $this->assertSame('approved', $g->fresh()->status);
        $this->assertSame('approved', GenericCompany::where('name', 'GSK')->value('status'));

        // G-9: approved → the business can't change it any more.
        $this->asOwner()->get(route('generic_products.edit', $g->id))->assertForbidden();
        $this->asOwner()->put(route('generic_products.update', $g->id), $this->payload(['product_name' => 'Hijacked']))->assertSessionHasErrors('entry');
        $this->assertSame('Calpol Plus', $g->fresh()->product_name);

        // Now importable with its packaging.
        $this->asOwner()->post(route('generic_products.import'), ['product_id' => $g->id])->assertSessionHas('success');
        $this->assertFalse((bool) Product::where('generic_product_id', $g->id)->value('is_draft'));
    }

    public function test_g7_g8_only_the_admin_reviews_and_the_old_open_endpoints_are_gone(): void
    {
        $g = GenericProduct::create(['product_name' => 'X', 'status' => 'pending', 'generic_company_id' => GenericCompany::create(['name' => 'C', 'status' => 'approved'])->id,
            'generic_product_type_id' => \App\Models\GenericProductType::create(['name' => 'T', 'status' => 'approved'])->id]);

        $this->asOwner()->post(route('generic_products.review'), ['action' => 'approve', 'ids' => [$g->id]])->assertSessionHasErrors('entry');
        $this->assertSame('pending', $g->fresh()->status);
        $this->asOwner()->get("/admin/generic-products/{$g->id}/approve")->assertStatus(404);              // G-8: no GET approve
        $this->asOwner()->post("/admin/generic-products/{$g->id}/parameters", ['parameters' => []])->assertStatus(404); // G-7
        $this->asOwner()->post('/admin/generic-product-categories/store', ['product_id' => $g->id])->assertStatus(404);
        $this->asOwner()->get('/admin/generic-products/sync-all')->assertStatus(404);

        $this->asAdmin()->post(route('generic_products.review'), ['action' => 'reject', 'ids' => [$g->id]])->assertSessionHas('success');
        $this->assertSame('rejected', $g->fresh()->status);
    }

    public function test_admin_adds_approved_entries_with_the_packaging_rules_and_no_duplicates(): void
    {
        $this->asAdmin()->get(route('generic_products.index'))->assertInertia(fn (Assert $a) => $a->where('admin', true)->where('canImport', false));
        $this->asAdmin()->post(route('generic_products.suggest.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('approved', GenericProduct::where('product_name', 'Calpol')->value('status'));

        $this->asAdmin()->post(route('generic_products.suggest.store'), $this->payload(['strengths' => [['name' => '120mg/5ml']]]))->assertSessionHasErrors('product_name');
        $bad = $this->payload(['product_name' => 'Bad']);
        $bad['levels'][1]['sale_price'] = 50;   // below purchase
        $bad['levels'][1]['quantity'] = 2.5;    // not whole
        $this->asAdmin()->post(route('generic_products.suggest.store'), $bad)->assertSessionHasErrors(['levels.1.sale_price', 'levels.1.quantity']);
        $dup = $this->payload(['product_name' => 'Dup']);
        $dup['levels'][1]['unit'] = ['name' => 'Carton'];
        $this->asAdmin()->post(route('generic_products.suggest.store'), $dup)->assertSessionHasErrors('levels');
        $this->assertSame(1, GenericProduct::count());

        $this->get(route('generic_products.suggestions'))->assertRedirect(route('generic_products.index', ['status' => 'pending']));
    }
}
