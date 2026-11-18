<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\User;
use App\Services\Billing\Exceptions\BillingException;
use App\Services\Billing\BillingService;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/**
 * One business = one business type (owner's rule 2026-11-19, docs/BUSINESS_TYPES_AND_SERVICES.md):
 * chosen once and confirmed, never changed; locked modules always on; modules of another type refused everywhere.
 */
class BusinessTypeRuleTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private function modules(): ModuleManager
    {
        app()->forgetScopedInstances();
        $m = app(ModuleManager::class);
        $m->forget();

        return $m;
    }

    private function make(string $type, string $name = 'Biz'): Business
    {
        return Business::create(['name' => $name, 'business_type' => $type])->fresh();
    }

    public function test_each_type_starts_with_its_own_defaults_and_nothing_of_another_type(): void
    {
        $expect = [
            'pharmacy' => ['core', 'stock', 'pos', 'pharmacy', 'inventory_pro', 'accounting', 'expense'],
            'inventory' => ['core', 'stock', 'pos', 'inventory_pro', 'accounting', 'expense'],
            'inventory_b2b' => ['core', 'stock', 'pos', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense'],
        ];
        foreach ($expect as $type => $modules) {
            $business = $this->make($type, "A {$type}");
            $this->assertEqualsCanonicalizing($modules, $this->modules()->activeFor($business->id), $type);
        }
    }

    public function test_modules_of_another_type_are_refused(): void
    {
        $refused = [
            'pharmacy' => ['b2b_sales', 'b2b_purchase'],
            'inventory' => ['pharmacy', 'b2b_sales', 'b2b_purchase'],
            'inventory_b2b' => ['pharmacy'],
        ];
        foreach ($refused as $type => $modules) {
            $business = $this->make($type, "B {$type}");
            foreach ($modules as $module) {
                try {
                    $this->modules()->activate($business, $module);
                    $this->fail("{$type} must not get {$module}");
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString('create a new business', $e->getMessage());
                }
                $this->assertFalse($this->modules()->isActive($business->id, $module));
            }
        }
    }

    public function test_locked_modules_cannot_be_switched_off_but_micro_services_can(): void
    {
        $pharmacy = $this->make('pharmacy');
        foreach (['pharmacy', 'stock', 'pos'] as $locked) {
            try {
                $this->modules()->deactivate($pharmacy, $locked);
                $this->fail("{$locked} must stay on");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('cannot be switched off', $e->getMessage());
            }
        }
        foreach (['accounting', 'expense', 'inventory_pro'] as $service) {
            $this->modules()->deactivate($pharmacy, $service);
            $this->assertFalse($this->modules()->isActive($pharmacy->id, $service), $service);
        }
        $b2b = $this->make('inventory_b2b');
        $this->modules()->deactivate($b2b, 'b2b_sales'); // a micro service, on by default, switchable
        $this->assertFalse($this->modules()->isActive($b2b->id, 'b2b_sales'));
    }

    public function test_the_business_type_can_never_change(): void
    {
        $business = $this->make('pharmacy');

        $this->expectException(\DomainException::class);
        $business->update(['business_type' => 'inventory_b2b']);
    }

    public function test_other_fields_still_save(): void
    {
        $business = $this->make('inventory');
        $business->update(['name' => 'Renamed']);
        $this->assertSame('Renamed', $business->fresh()->name);
        $this->assertSame('inventory', $business->fresh()->business_type);
    }

    public function test_modules_page_lists_only_the_types_modules_and_refuses_others(): void
    {
        $this->withoutVite();
        $this->setUpBusiness('Corner Pharmacy');

        $this->get(route('modules.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Modules/Index')
            ->where('businessTypeName', 'Pharmacy')
            ->where('modules', fn ($list) => collect($list)->pluck('key')->intersect(['b2b_sales', 'b2b_purchase'])->isEmpty()
                && collect($list)->firstWhere('key', 'pharmacy')['locked'] === true
                && collect($list)->firstWhere('key', 'accounting')['locked'] === false));

        $this->post(route('modules.activate', 'b2b_sales'))->assertSessionHas('error');
        $this->assertFalse($this->modules()->isActive($this->business->id, 'b2b_sales'));

        $this->delete(route('modules.deactivate', 'pharmacy'))->assertSessionHas('error');
        $this->assertTrue($this->modules()->isActive($this->business->id, 'pharmacy'));
    }

    public function test_the_wizard_needs_the_final_choice_confirmed_and_only_type_modules(): void
    {
        $this->withoutVite();
        Role::findOrCreate('Business Owner');
        $user = User::create(['name' => 'New', 'email' => 'n@example.test', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        $this->get(route('business.create'))->assertInertia(fn (Assert $page) => $page
            ->where('types.0.key', 'pharmacy')
            ->where('types.0.locked', ['core', 'stock', 'pos', 'pharmacy'])
            ->where('types.1.name', 'Retail & Inventory')
            ->where('types.2.name', 'Wholesale & Distribution (B2B)'));

        $this->post(route('business.store'), ['name' => 'X', 'business_type' => 'pharmacy', 'modules' => []])
            ->assertSessionHasErrors('type_confirmed');
        $this->post(route('business.store'), ['name' => 'X', 'business_type' => 'pharmacy', 'modules' => ['b2b_sales'], 'type_confirmed' => true])
            ->assertSessionHasErrors('modules');
        $this->assertSame(0, Business::count());

        // Nothing chosen: the locked modules of the type stay on anyway.
        $this->post(route('business.store'), ['name' => 'Rana', 'business_type' => 'pharmacy', 'modules' => [], 'type_confirmed' => true])
            ->assertRedirect(route('generic_products.index'));
        $business = Business::where('name', 'Rana')->firstOrFail();
        $this->assertEqualsCanonicalizing(['core', 'stock', 'pos', 'pharmacy'], $this->modules()->activeFor($business->id));
    }

    public function test_billing_refuses_a_module_of_another_type(): void
    {
        $this->setUpBusiness('Billing Pharmacy');

        $this->expectException(BillingException::class);
        app(BillingService::class)->subscribe($this->business->fresh(), $this->owner, null, ['b2b_purchase'], 'monthly');
    }

    public function test_migration_archives_modules_of_another_type_and_switches_locked_ones_on(): void
    {
        $shop = $this->make('inventory', 'Mixed shop');
        // A business that mixed types before the rule (written straight to the table, as the old code allowed).
        foreach (['pharmacy', 'b2b_sales'] as $m) {
            BusinessModule::create(['business_id' => $shop->id, 'module' => $m, 'status' => 'active', 'source' => 'free']);
        }
        BusinessModule::where('business_id', $shop->id)->where('module', 'pos')->delete();

        (require database_path('migrations/2026_11_19_100000_enforce_business_type_modules.php'))->up();

        $active = $this->modules()->activeFor($shop->id);
        $this->assertNotContains('pharmacy', $active);
        $this->assertNotContains('b2b_sales', $active);
        $this->assertContains('pos', $active);
        $this->assertTrue(BusinessModule::withTrashed()->where('business_id', $shop->id)->where('module', 'b2b_sales')->exists(), 'archived, not deleted');
    }
}
