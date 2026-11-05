<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\User;
use App\Services\Access\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 4i-1: per-business roles, module permission catalogue, Gate integration. */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
    }

    private function member(string $pivotRole, ?BusinessRole $role = null): User
    {
        $user = User::create(['name' => ucfirst($pivotRole), 'email' => $pivotRole.uniqid().'@example.test', 'password' => bcrypt('x')]);
        $user->businesses()->attach($this->business->id, ['role' => $pivotRole, 'business_role_id' => $role?->id]);

        return $user;
    }

    private function can(User $user, string $permission): bool
    {
        app()->forgetScopedInstances();
        $this->actingAs($user)->withSession(['business_id' => $this->business->id]);
        session(['business_id' => $this->business->id]);

        return $user->can($permission);
    }

    public function test_every_business_gets_owner_and_sales_person_roles(): void
    {
        $new = Business::create(['name' => 'New']);

        $this->assertEqualsCanonicalizing(['owner', 'sales-person'], BusinessRole::where('business_id', $new->id)->pluck('system_key')->all());
        $sales = BusinessRole::where('business_id', $new->id)->where('system_key', 'sales-person')->first();
        $this->assertEqualsCanonicalizing(['pos.sell', 'pos.discount', 'pos.invoices.view'], $sales->permissionKeys());
    }

    public function test_owner_has_every_permission_of_active_modules_only(): void
    {
        $this->assertTrue($this->can($this->owner, 'pos.profit.view'));
        $this->assertTrue($this->can($this->owner, 'stock.purchases.manage'));

        $this->delete(route('modules.deactivate', 'pharmacy'));
        $this->assertFalse($this->can($this->owner, 'pharmacy.generic.import'), 'module off → permission has no effect');
    }

    public function test_sales_person_and_custom_roles(): void
    {
        $cashier = $this->member('sales-person');
        $this->assertTrue($this->can($cashier, 'pos.sell'));
        $this->assertFalse($this->can($cashier, 'pos.profit.view'));

        $manager = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Manager']);
        $manager->syncPermissions(['pos.sell', 'pos.returns.manage', 'stock.purchases.view']);
        $user = $this->member('staff', $manager);

        $this->assertTrue($this->can($user, 'pos.returns.manage'));
        $this->assertTrue($this->can($user, 'stock.purchases.view'));
        $this->assertFalse($this->can($user, 'stock.purchases.manage'));
        $this->assertFalse($this->can($user, 'core.users.manage'));
    }

    public function test_suspended_members_and_other_businesses_are_denied(): void
    {
        $cashier = $this->member('sales-person');
        DB::table('business_user')->where('user_id', $cashier->id)->update(['status' => 'suspended']);
        $this->assertFalse($this->can($cashier, 'pos.sell'));

        $outsider = User::create(['name' => 'Outsider', 'email' => 'out@example.test', 'password' => bcrypt('x')]);
        $this->assertFalse($this->can($outsider, 'pos.sell'));
    }

    public function test_super_admin_passes_and_legacy_spatie_permissions_still_work(): void
    {
        $admin = User::create(['name' => 'Root', 'email' => 'root4i@example.test', 'password' => bcrypt('x')]);
        $admin->assignRole(Role::findOrCreate('super-admin'));
        $this->assertTrue($this->can($admin, 'pos.profit.view'));

        Permission::findOrCreate('view-category');
        $this->assertFalse($this->can($this->owner, 'view-category'));
        $this->owner->givePermissionTo('view-category');
        $this->assertTrue($this->can($this->owner->fresh(), 'view-category'));
    }

    public function test_catalogue_lists_permissions_per_module(): void
    {
        $catalogue = app(PermissionService::class)->catalogue();

        $this->assertSame(['core', 'stock', 'pos', 'pharmacy'], array_keys($catalogue));
        $this->assertArrayHasKey('pos.price_change', $catalogue['pos']['permissions']);
    }
}
