<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\Preference;
use App\Models\User;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4b: module registry, business type, module gating. */
class ModulesTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha Pharmacy');
    }

    private function modules(): ModuleManager
    {
        app()->forgetScopedInstances();

        return app(ModuleManager::class);
    }

    public function test_a_new_business_gets_the_default_modules_for_its_type(): void
    {
        $this->assertEqualsCanonicalizing(['core', 'stock', 'pos', 'pharmacy'], $this->modules()->activeFor($this->business->id));

        $shop = Business::create(['name' => 'Corner Shop', 'business_type' => 'inventory']);
        // inventory_pro is not built yet ("coming soon"), so it is skipped for now.
        $this->assertEqualsCanonicalizing(['core', 'stock', 'pos'], $this->modules()->activeFor($shop->id));
    }

    public function test_screens_of_a_switched_off_module_are_blocked(): void
    {
        $this->delete(route('modules.deactivate', 'pos'))->assertSessionHas('success');

        $this->postJson(route('pos.save-invoice'), ['cart' => []])
            ->assertForbidden()->assertJsonPath('error', 'module_inactive')->assertJsonPath('module', 'pos');
        $this->get(route('pos.index'))->assertForbidden()->assertInertia(fn (Assert $page) => $page
            ->component('Modules/Locked')->where('module.key', 'pos'));

        // Other modules keep working.
        $this->get(route('taxes.index'))->assertOk();

        $this->post(route('modules.activate', 'pos'))->assertSessionHas('success');
        $this->get(route('pos.index'))->assertOk();
    }

    public function test_dependencies_are_switched_on_and_protected(): void
    {
        $this->delete(route('modules.deactivate', 'stock'))->assertSessionHas('error');
        $this->assertTrue($this->modules()->isActive($this->business->id, 'stock'));

        $this->delete(route('modules.deactivate', 'pos'));
        $this->delete(route('modules.deactivate', 'pharmacy'));
        $this->delete(route('modules.deactivate', 'stock'))->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(['core'], $this->modules()->activeFor($this->business->id));

        $this->post(route('modules.activate', 'pos'));
        $this->assertEqualsCanonicalizing(['core', 'stock', 'pos'], $this->modules()->activeFor($this->business->id));
    }

    public function test_core_and_coming_soon_modules_cannot_be_changed(): void
    {
        $this->delete(route('modules.deactivate', 'core'))->assertSessionHas('error');
        $this->post(route('modules.activate', 'b2b_sales'))->assertSessionHas('error');
        $this->post(route('modules.activate', 'nope'))->assertNotFound();
        $this->assertFalse($this->modules()->isActive($this->business->id, 'b2b_sales'));
    }

    public function test_only_the_owner_can_change_modules(): void
    {
        // Owns a different business (global "Business Owner" role) but sells in this one.
        $member = User::create(['name' => 'Member', 'email' => 'mgr@example.test', 'password' => bcrypt('x')]);
        $member->assignRole(Role::findOrCreate('Business Owner'));
        $member->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $member);

        $this->post(route('modules.activate', 'pharmacy'))->assertRedirect(route('pos.index'));
        $this->delete(route('modules.deactivate', 'pos'))->assertRedirect(route('pos.index'));
        $this->assertTrue($this->modules()->isActive($this->business->id, 'pos'));
    }

    public function test_roles_are_per_business(): void
    {
        // A sales-person here can own another business and is NOT restricted there.
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier2@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole(Role::findOrCreate('sales-person'));
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $ownShop = Business::create(['name' => 'Own Shop']);
        $cashier->businesses()->attach($ownShop->id, ['role' => 'owner']);

        $this->enterBusiness($this->business, $cashier);
        $this->get(route('taxes.index'))->assertRedirect(route('pos.index'));

        $this->enterBusiness($ownShop, $cashier);
        $this->get(route('taxes.index'))->assertOk();
        $this->get(route('modules.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canManage', true)->where('auth.user.is_owner', true));
        $this->delete(route('modules.deactivate', 'pharmacy'))->assertSessionHas('success');
    }

    public function test_super_admin_outside_impersonation_is_not_blocked(): void
    {
        $this->delete(route('modules.deactivate', 'pos'));

        $admin = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('x')]);
        $admin->assignRole(Role::findOrCreate('super-admin'));
        $this->actingAs($admin)->withSession(['business_id' => $this->business->id]);

        $this->get(route('pos.index'))->assertOk();
    }

    public function test_navigation_marks_switched_off_modules_as_locked(): void
    {
        $this->delete(route('modules.deactivate', 'pos'));

        $nav = collect($this->get(route('modules.index'))->viewData('page')['props']['navigation'])
            ->flatMap(fn ($g) => $g['items'])->keyBy('label');

        $this->assertTrue($nav['POS']['locked']);
        $this->assertStringContainsString('modules?module=pos', $nav['POS']['href']);
        $this->assertArrayNotHasKey('locked', $nav['Dashboard']);
        $this->assertTrue($nav['Modules']['inertia']);
    }

    public function test_activating_stock_seeds_its_preferences(): void
    {
        $bare = Business::create(['name' => 'Bare', 'business_type' => 'inventory']);
        Preference::withoutGlobalScopes()->where('business_id', $bare->id)->forceDelete();
        BusinessModule::where('business_id', $bare->id)->whereIn('module', ['stock', 'pos'])->delete();

        $this->modules()->activate($bare, 'stock');

        $this->assertSame(10, Preference::withoutGlobalScopes()->where('business_id', $bare->id)->count());
    }

    public function test_wording_follows_the_business_type(): void
    {
        $this->business->update(['business_type' => 'inventory_b2b']);

        $this->get(route('modules.index'))->assertInertia(fn (Assert $page) => $page
            ->where('business.type', 'inventory_b2b')
            ->where('wording.products', 'Items')
            ->where('wording.suppliers', 'Vendors'));
    }

    public function test_module_changes_are_audited(): void
    {
        $this->delete(route('modules.deactivate', 'pharmacy'));

        $this->assertTrue(\App\Models\AuditLog::where('action', 'deleted')->where('summary', 'like', '%pharmacy%')->exists());
    }
}
