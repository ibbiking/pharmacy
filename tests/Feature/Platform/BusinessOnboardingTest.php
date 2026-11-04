<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\Currency\GlobalCurrency;
use App\Models\User;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4c: create-business wizard. */
class BusinessOnboardingTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private User $newcomer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Role::findOrCreate('Business Owner');
        Role::findOrCreate('sales-person');
        GlobalCurrency::create(['currency_code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);
        GlobalCurrency::create(['currency_code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
        $this->newcomer = User::create(['name' => 'New Owner', 'email' => 'new@example.test', 'password' => bcrypt('x')]);
    }

    private function activeModules(int $businessId): array
    {
        app()->forgetScopedInstances();

        return app(ModuleManager::class)->activeFor($businessId);
    }

    public function test_a_user_without_a_business_is_sent_to_the_wizard(): void
    {
        $this->actingAs($this->newcomer);

        $this->get(route('dashboard'))->assertRedirect(route('business.create'));
        $this->get('/admin/business/setup')->assertRedirect('/admin/business/create');
        $this->get(route('business.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Business/Create')
            ->where('isFirstBusiness', true)
            ->where('defaultCurrency.currency_code', 'PKR')
            ->has('types', 3)
            ->has('modules', 11));
    }

    public function test_creating_a_business_applies_type_modules_owner_and_switches_to_it(): void
    {
        $this->actingAs($this->newcomer);
        $usd = GlobalCurrency::where('currency_code', 'USD')->value('id');

        $this->post(route('business.store'), [
            'name' => 'Corner Shop', 'business_type' => 'inventory', 'currency_id' => $usd,
            'modules' => ['pos'], 'phone' => '0300', 'address' => 'Main Bazar',
        ])->assertRedirect(route('dashboard'))->assertSessionHas('success');

        $business = Business::where('name', 'Corner Shop')->firstOrFail();
        $this->assertSame('inventory', $business->business_type);
        $this->assertSame($usd, (int) $business->currency_id);
        $this->assertSame('owner', $this->newcomer->roleIn($business->id));
        $this->assertTrue($this->newcomer->fresh()->hasRole('Business Owner'));
        $this->assertSame($business->id, session('business_id'));
        // POS needs Stock, so both are on; nothing else.
        $this->assertEqualsCanonicalizing(['core', 'stock', 'pos'], $this->activeModules($business->id));
    }

    public function test_unchosen_recommended_modules_are_switched_off(): void
    {
        $this->actingAs($this->newcomer);

        $this->post(route('business.store'), ['name' => 'Tiny', 'business_type' => 'pharmacy', 'modules' => []])
            ->assertRedirect(route('dashboard'));

        $business = Business::where('name', 'Tiny')->firstOrFail();
        $this->assertEqualsCanonicalizing(['core'], $this->activeModules($business->id));
    }

    public function test_a_pharmacy_with_the_pharmacy_pack_lands_on_the_generic_catalogue(): void
    {
        $this->actingAs($this->newcomer);

        $this->post(route('business.store'), ['name' => 'Rana Medical', 'business_type' => 'pharmacy', 'modules' => ['stock', 'pos', 'pharmacy']])
            ->assertRedirect(route('generic_products.index'));
    }

    public function test_validation(): void
    {
        $this->actingAs($this->newcomer);
        $someoneElse = Business::create(['name' => 'Someone else']);
        $ownCurrency = GlobalCurrency::create(['currency_code' => 'XYZ', 'name' => 'Private', 'symbol' => 'x', 'business_id' => $someoneElse->id]);

        $this->post(route('business.store'), ['name' => '', 'business_type' => 'bakery', 'modules' => ['b2b_sales', 'core'], 'currency_id' => $ownCurrency->id])
            ->assertSessionHasErrors(['name', 'business_type', 'modules.0', 'modules.1', 'currency_id']);
        $this->assertSame(1, Business::count(), 'only the pre-existing business');
    }

    public function test_an_owner_can_add_another_business_and_keep_both(): void
    {
        $this->setUpBusiness('First Shop');

        $this->get(route('business.create'))->assertInertia(fn (Assert $page) => $page->where('isFirstBusiness', false));
        $this->post(route('business.store'), ['name' => 'Second Shop', 'business_type' => 'pharmacy', 'modules' => ['pos']]);

        $this->assertSame(2, $this->owner->businesses()->count());
        $this->assertSame(Business::where('name', 'Second Shop')->value('id'), session('business_id'));
    }

    public function test_a_sales_person_can_create_their_own_business_and_own_it(): void
    {
        $this->setUpBusiness('Employer');
        $cashier = User::create(['name' => 'Cashier', 'email' => 'c@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $cashier);

        $this->post(route('business.store'), ['name' => 'Side Shop', 'business_type' => 'inventory', 'modules' => ['pos']])
            ->assertRedirect(route('dashboard'));
        $this->get(route('taxes.index'))->assertOk(); // not restricted in their own business

        $this->post(route('business.switch'), ['business_id' => $this->business->id]);
        $this->get(route('taxes.index'))->assertRedirect(route('pos.index')); // still a sales-person at the employer
    }

    public function test_currency_search_is_paginated(): void
    {
        $this->actingAs($this->newcomer);
        foreach (range(1, 30) as $i) {
            GlobalCurrency::create(['currency_code' => sprintf('C%02d', $i), 'name' => "Currency {$i}", 'symbol' => 'c']);
        }

        $first = $this->getJson(route('business.create.currencies'))->assertOk();
        $this->assertCount(20, $first->json('data'));
        $this->assertStringStartsWith('PKR', $first->json('data.0.label'));
        $this->assertSame(2, $first->json('next_page'));
        $this->getJson(route('business.create.currencies', ['q' => 'dollar']))->assertJsonPath('total', 1);
    }
}
