<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 3: Inertia foundation, shared props, lookup API, business switching, Taxes pilot. */
class InertiaFoundationTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha Pharmacy');
        foreach (['view-category', 'view-products', 'view-purchase', 'view-reports'] as $p) {
            Permission::findOrCreate($p);
        }
        $this->owner->givePermissionTo(['view-category', 'view-products']);
    }

    // ------------------------------------------------------------------ shared props & navigation

    public function test_shared_props_carry_user_business_wording_and_navigation(): void
    {
        $this->get(route('taxes.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Taxes/Index')
            ->where('auth.user.id', $this->owner->id)
            ->where('auth.user.theme', 'system')
            ->where('business.name', 'Alpha Pharmacy')
            ->where('business.type', 'pharmacy')
            ->where('wording.products', 'Medicines')
            ->has('navigation')
            ->where('businesses.count', 1));
    }

    public function test_navigation_respects_permissions_and_marks_inertia_screens(): void
    {
        $navigation = $this->get(route('taxes.index'))->viewData('page')['props']['navigation'];
        $flat = collect($navigation)->flatMap(fn ($g) => $g['items'])
            ->flatMap(fn ($i) => array_merge([$i], $i['children'] ?? []));

        $taxes = $flat->firstWhere('label', 'Taxes');
        $this->assertTrue($taxes['inertia']);
        $this->assertTrue($taxes['active']);
        $this->assertNotNull($flat->firstWhere('label', 'Medicines'), 'products group uses pharmacy wording');
        $this->assertNull($flat->firstWhere('label', 'Purchases'), 'no view-purchase permission');
        $this->assertNull($flat->firstWhere('label', 'Manage Businesses'), 'super-admin only');
    }

    // ------------------------------------------------------------------ Taxes pilot

    public function test_tax_list_is_paginated_searchable_and_sortable_on_the_server(): void
    {
        foreach (range(1, 25) as $i) {
            Tax::create(['name' => sprintf('Tax %02d', $i), 'rate' => $i]);
        }

        $this->get(route('taxes.index'))->assertInertia(fn (Assert $page) => $page
            ->has('taxes.data', 20)
            ->where('taxes.total', 25)
            ->where('taxes.last_page', 2)
            ->where('stats.count', 25)
            ->where('taxes.data.0.name', 'Tax 01'));

        $this->get(route('taxes.index', ['q' => 'Tax 1']))->assertInertia(fn (Assert $page) => $page->where('taxes.total', 10));

        $this->get(route('taxes.index', ['sort' => 'rate', 'dir' => 'desc', 'per_page' => 10]))->assertInertia(fn (Assert $page) => $page
            ->has('taxes.data', 10)
            ->where('taxes.data.0.rate', 25)
            ->where('filters.sort', 'rate'));

        // Unknown sort columns and page sizes fall back to safe defaults.
        $this->get(route('taxes.index', ['sort' => 'id;drop table', 'per_page' => 5000]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name')->where('filters.per_page', 20));
    }

    public function test_stat_strip_usage_filter_lists_used_or_unused_taxes(): void
    {
        [$product, $u] = $this->makeProduct('Syrup', ['Bottle' => 1]);
        $used = $this->tax('Used Tax', 17);
        $this->tax('Spare Tax', 5);
        $this->purchase($product, $u['Bottle'], 1, 80, 100, now()->addYear()->toDateString(), [[$used, 17]]);

        $this->get(route('taxes.index', ['usage' => 'used']))->assertInertia(fn (Assert $page) => $page
            ->where('taxes.total', 1)->where('taxes.data.0.name', 'Used Tax')->where('taxes.data.0.usage', 1)
            ->where('stats.in_use', 1)->where('filters.usage', 'used'));
        $this->get(route('taxes.index', ['usage' => 'unused']))->assertInertia(fn (Assert $page) => $page
            ->where('taxes.total', 1)->where('taxes.data.0.name', 'Spare Tax'));
        $this->get(route('taxes.index', ['usage' => 'bogus']))->assertInertia(fn (Assert $page) => $page
            ->where('taxes.total', 2)->where('filters.usage', ''));
    }

    public function test_tax_names_are_unique_per_business_not_globally(): void
    {
        $this->post(route('taxes.store'), ['name' => 'GST', 'rate' => 17])->assertSessionHasNoErrors();
        $this->post(route('taxes.store'), ['name' => 'GST', 'rate' => 18])->assertSessionHasErrors('name');

        $other = User::create(['name' => 'B owner', 'email' => 'b@example.test', 'password' => bcrypt('x')]);
        $beta = Business::create(['name' => 'Beta Store']);
        $other->businesses()->attach($beta->id, ['role' => 'owner']);
        $this->enterBusiness($beta, $other);

        $this->post(route('taxes.store'), ['name' => 'GST', 'rate' => 16])->assertSessionHasNoErrors();
        $this->assertSame(2, Tax::withoutGlobalScopes()->where('name', 'GST')->count());
        $this->get(route('taxes.index'))->assertInertia(fn (Assert $page) => $page->where('taxes.total', 1)->where('taxes.data.0.rate', 16));
    }

    public function test_tax_validation_update_and_archive(): void
    {
        $this->post(route('taxes.store'), ['name' => '', 'rate' => 150])->assertSessionHasErrors(['name', 'rate']);

        $this->post(route('taxes.store'), ['name' => 'VAT', 'rate' => 10]);
        $tax = Tax::firstWhere('name', 'VAT');

        $this->put(route('taxes.update', $tax), ['name' => 'VAT', 'rate' => 12])->assertSessionHasNoErrors();
        $this->assertEquals(12, (float) $tax->fresh()->rate);

        $this->delete(route('taxes.destroy', $tax))->assertSessionHas('success');
        $this->assertSoftDeleted($tax);

        // An archived name can be reused.
        $this->post(route('taxes.store'), ['name' => 'VAT', 'rate' => 13])->assertSessionHasNoErrors();
    }

    public function test_a_business_cannot_edit_or_archive_another_business_tax(): void
    {
        $tax = Tax::create(['name' => 'Alpha only', 'rate' => 5]);

        $other = User::create(['name' => 'B owner', 'email' => 'b2@example.test', 'password' => bcrypt('x')]);
        $beta = Business::create(['name' => 'Beta Store']);
        $other->businesses()->attach($beta->id, ['role' => 'owner']);
        $this->enterBusiness($beta, $other);

        $this->put(route('taxes.update', $tax->id), ['name' => 'Hacked', 'rate' => 1])->assertNotFound();
        $this->delete(route('taxes.destroy', $tax->id))->assertNotFound();
        $this->assertSame('Alpha only', Tax::withoutGlobalScopes()->find($tax->id)->name);
    }

    public function test_old_create_and_edit_links_open_the_new_screen(): void
    {
        $tax = Tax::create(['name' => 'GST', 'rate' => 17]);

        $this->get(route('taxes.create'))->assertRedirect(route('taxes.index', ['create' => 1]));
        $this->get(route('taxes.edit', $tax))->assertRedirect(route('taxes.index', ['edit' => $tax->id]));
        $this->get(route('taxes.index', ['edit' => $tax->id]))->assertInertia(fn (Assert $page) => $page->where('open', $tax->id));
    }

    // ------------------------------------------------------------------ lookup API

    public function test_lookup_api_pages_and_searches_within_the_business(): void
    {
        foreach (range(1, 45) as $i) {
            Tax::create(['name' => sprintf('Tax %02d', $i), 'rate' => $i]);
        }

        $page1 = $this->getJson(route('lookup', ['entity' => 'taxes']))->assertOk();
        $this->assertCount(20, $page1->json('data'));
        $this->assertSame(2, $page1->json('next_page'));
        $this->assertSame(45, $page1->json('total'));
        $this->assertEquals(['rate' => 1], $page1->json('data.0.meta'));

        $page3 = $this->getJson(route('lookup', ['entity' => 'taxes', 'page' => 3]))->assertOk();
        $this->assertCount(5, $page3->json('data'));
        $this->assertNull($page3->json('next_page'));

        $this->getJson(route('lookup', ['entity' => 'taxes', 'q' => 'Tax 4']))->assertJsonPath('total', 6)->assertJsonPath('data.0.label', 'Tax 40');
        $this->getJson(route('lookup', ['entity' => 'users']))->assertNotFound();

        $other = User::create(['name' => 'B owner', 'email' => 'b3@example.test', 'password' => bcrypt('x')]);
        $beta = Business::create(['name' => 'Beta Store']);
        $other->businesses()->attach($beta->id, ['role' => 'owner']);
        $this->enterBusiness($beta, $other);
        $this->getJson(route('lookup', ['entity' => 'taxes']))->assertJsonPath('total', 0);
    }

    // ------------------------------------------------------------------ business switching & theme

    public function test_user_can_switch_only_to_their_own_businesses(): void
    {
        $second = Business::create(['name' => 'Second Branch']);
        $this->owner->businesses()->attach($second->id, ['role' => 'owner']);
        $stranger = Business::create(['name' => 'Not mine']);

        $this->post(route('business.switch'), ['business_id' => $stranger->id])->assertForbidden();

        $this->post(route('business.switch'), ['business_id' => $second->id])->assertRedirect(route('dashboard'));
        $this->assertSame($second->id, session('business_id'));

        $this->get(route('taxes.index'))->assertInertia(fn (Assert $page) => $page
            ->where('business.name', 'Second Branch')->where('businesses.count', 2));
    }

    public function test_inertia_redirects_to_classic_pages_become_full_page_loads(): void
    {
        $second = Business::create(['name' => 'Second Branch']);
        $this->owner->businesses()->attach($second->id, ['role' => 'owner']);

        // Switch business from an Inertia screen → dashboard is still a classic page → 409 location.
        $this->post(route('business.switch'), ['business_id' => $second->id], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('dashboard'));
        $this->assertSame('Switched business.', session('message'));
        $this->assertSame('success', session('alert-type'));

        // Redirects to Inertia screens stay normal Inertia redirects.
        $this->from(route('taxes.index'))->post(route('taxes.store'), ['name' => 'GST', 'rate' => 17], ['X-Inertia' => 'true'])->assertRedirect(route('taxes.index'));

        // Non-Inertia requests are untouched.
        $this->post(route('business.switch'), ['business_id' => $this->business->id])->assertRedirect(route('dashboard'));
    }

    public function test_theme_preference_is_saved_and_validated(): void
    {
        $this->post(route('preferences.theme'), ['theme' => 'dark'])->assertRedirect();
        $this->assertSame('dark', $this->owner->fresh()->theme);

        $this->post(route('preferences.theme'), ['theme' => 'neon'])->assertSessionHasErrors('theme');
        $this->assertSame('dark', $this->owner->fresh()->theme);
    }

    public function test_sales_person_can_switch_business_and_theme_but_not_open_taxes(): void
    {
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cash@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $cashier);

        $this->get(route('taxes.index'))->assertRedirect(route('pos.index'));
        $this->post(route('preferences.theme'), ['theme' => 'light'])->assertRedirect();
        $this->post(route('business.switch'), ['business_id' => $this->business->id])->assertRedirect(route('pos.index'));
    }
}
