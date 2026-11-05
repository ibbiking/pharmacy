<?php

namespace Tests\Feature\Platform;

use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Billing\PricingService;
use Database\Seeders\BillingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4d: module prices, plans, launch pricing. */
class BillingCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        PlatformSetting::flushCache();
        $this->setUpBusiness();
        (new BillingCatalogSeeder)->run();
        $this->admin = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('x')]);
        $this->admin->assignRole(Role::findOrCreate('super-admin'));
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['business_id' => $this->business->id]);
    }

    public function test_only_super_admins_can_open_plans_and_pricing(): void
    {
        $this->get(route('superadmin.billing'))->assertForbidden();

        $this->asAdmin()->get(route('superadmin.billing'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/Billing')
            ->has('modulePrices', 10)
            ->where('plans.total', 4)
            ->where('settings.free_for_everyone', true)
            ->where('settings.free_now', true));
    }

    public function test_module_price_update_and_validation(): void
    {
        $this->asAdmin()->put(route('superadmin.billing.module-price', 'pos'), ['monthly_price' => 19.5, 'yearly_price' => 190, 'currency' => 'pkr'])
            ->assertSessionHasNoErrors();
        $this->assertEquals([19.5, 190.0, 'PKR'], array_values(ModulePrice::where('module', 'pos')->first()->only('monthly_price', 'yearly_price', 'currency')));

        $this->put(route('superadmin.billing.module-price', 'pos'), ['monthly_price' => -1, 'yearly_price' => 'x', 'currency' => 'RUPEE'])
            ->assertSessionHasErrors(['monthly_price', 'yearly_price', 'currency']);
        $this->put(route('superadmin.billing.module-price', 'core'), ['monthly_price' => 1, 'yearly_price' => 1, 'currency' => 'USD'])->assertNotFound();
    }

    public function test_plans_include_dependencies_and_get_a_unique_slug(): void
    {
        $this->asAdmin()->post(route('superadmin.billing.plans.store'), [
            'name' => 'Counter Only', 'modules' => ['pos'], 'monthly_price' => 9, 'yearly_price' => 90, 'currency' => 'usd',
            'limits' => ['users' => 3, 'warehouses' => '', 'bogus' => 5], 'trial_days' => 14, 'is_visible' => true,
        ])->assertSessionHasNoErrors();

        $plan = Plan::where('slug', 'counter-only')->firstOrFail();
        $this->assertEqualsCanonicalizing(['pos', 'stock'], $plan->modules);
        $this->assertSame(['users' => 3, 'warehouses' => null], $plan->limits);
        $this->assertSame('USD', $plan->currency);

        $this->post(route('superadmin.billing.plans.store'), ['name' => 'Counter Only', 'modules' => ['pos'], 'monthly_price' => 1, 'yearly_price' => 1, 'currency' => 'USD', 'trial_days' => 0, 'is_visible' => true])
            ->assertSessionHasErrors('slug');
        $this->post(route('superadmin.billing.plans.store'), ['name' => 'Bad', 'modules' => ['core', 'nope'], 'monthly_price' => 1, 'yearly_price' => 1, 'currency' => 'USD', 'trial_days' => 200, 'is_visible' => true])
            ->assertSessionHasErrors(['modules.0', 'modules.1', 'trial_days']);

        $this->delete(route('superadmin.billing.plans.destroy', $plan))->assertSessionHas('success');
        $this->assertSoftDeleted($plan);
    }

    public function test_launch_pricing_switch_controls_module_activation(): void
    {
        $this->delete(route('modules.deactivate', 'pharmacy'));

        $this->asAdmin()->put(route('superadmin.billing.settings'), ['free_for_everyone' => false])->assertSessionHasNoErrors();
        PlatformSetting::flushCache();

        $this->actingAs($this->owner)->withSession(['business_id' => $this->business->id]);
        // No subscription covers it: the owner is sent to Billing with the module preselected.
        $this->post(route('modules.activate', 'pharmacy'))->assertRedirect(route('billing.index', ['modules' => ['pharmacy']]))->assertSessionHas('info');
        $this->get(route('modules.index'))->assertInertia(fn (Assert $page) => $page->where('free', false));

        // A grace period keeps it free until that date.
        $this->asAdmin()->put(route('superadmin.billing.settings'), ['free_for_everyone' => false, 'free_grace_until' => now()->addDays(10)->toDateString()]);
        PlatformSetting::flushCache();
        $this->actingAs($this->owner)->withSession(['business_id' => $this->business->id]);
        $this->post(route('modules.activate', 'pharmacy'))->assertSessionHas('success');

        $this->asAdmin()->put(route('superadmin.billing.settings'), ['free_for_everyone' => false, 'free_grace_until' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors('free_grace_until');
    }

    public function test_pricing_quote_adds_dependencies_and_respects_launch_pricing(): void
    {
        $quote = app(PricingService::class)->quote(['pos']);
        $this->assertTrue($quote['free']);
        $this->assertSame(0.0, $quote['total']);
        $this->assertEqualsCanonicalizing(['pos', 'stock'], array_column($quote['modules'], 'key'));

        PlatformSetting::put('free_for_everyone', false);
        $paid = app(PricingService::class)->quote(['pos', 'pharmacy'], 'yearly');
        $this->assertFalse($paid['free']);
        $this->assertSame(150.0 + 150.0 + 100.0, $paid['total']);
        $this->assertTrue(collect($paid['modules'])->firstWhere('key', 'stock')['added_for_dependency']);
    }

    public function test_catalog_seeder_is_repeatable_and_keeps_edits(): void
    {
        ModulePrice::where('module', 'pos')->update(['monthly_price' => 77]);
        (new BillingCatalogSeeder)->run();

        $this->assertSame(10, ModulePrice::count());
        $this->assertSame(4, Plan::withTrashed()->count());
        $this->assertEquals(77, ModulePrice::where('module', 'pos')->value('monthly_price'));
    }

    public function test_setting_changes_are_audited(): void
    {
        $this->asAdmin()->put(route('superadmin.billing.settings'), ['free_for_everyone' => false]);

        $this->assertTrue(\App\Models\AuditLog::withoutGlobalScopes()->where('action', 'updated')->where('meta->key', 'free_for_everyone')->exists());
    }
}
