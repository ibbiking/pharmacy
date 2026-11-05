<?php

namespace Tests\Feature\Platform;

use App\Models\BusinessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 4i-2: role editor. */
class RoleEditorTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_roles_page_lists_built_in_roles_and_the_catalogue(): void
    {
        $this->get(route('settings.roles'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Roles')
            ->where('roles.total', 2)
            ->where('roles.data.0.system_key', 'owner')
            ->where('roles.data.0.permissions', ['*'])
            ->where('roles.data.0.members', 1)
            ->has('catalogue', 4)
            ->where('catalogue.2.module', 'pos'));
    }

    public function test_create_and_update_a_custom_role(): void
    {
        $this->post(route('settings.roles.store'), ['name' => 'Store manager', 'permissions' => ['pos.sell', 'stock.purchases.manage'], 'limits' => ['max_discount_percent' => 10]])
            ->assertSessionHasNoErrors();
        $role = BusinessRole::where('name', 'Store manager')->firstOrFail();
        $this->assertEqualsCanonicalizing(['pos.sell', 'stock.purchases.manage'], $role->permissionKeys());
        $this->assertEquals(['max_discount_percent' => 10], $role->limits);

        $this->post(route('settings.roles.store'), ['name' => 'Store manager', 'permissions' => ['made.up']])
            ->assertSessionHasErrors(['name', 'permissions.0']);

        $this->put(route('settings.roles.update', $role), ['name' => 'Manager', 'permissions' => ['pos.sell'], 'limits' => ['max_discount_percent' => '']])->assertSessionHasNoErrors();
        $this->assertSame(['pos.sell'], $role->fresh()->permissionKeys());
        $this->assertNull($role->fresh()->limits);
    }

    public function test_built_in_roles_are_protected(): void
    {
        $owner = BusinessRole::where('business_id', $this->business->id)->where('system_key', 'owner')->first();
        $sales = BusinessRole::where('business_id', $this->business->id)->where('system_key', 'sales-person')->first();

        $this->put(route('settings.roles.update', $owner), ['name' => 'Boss', 'permissions' => []])->assertForbidden();
        $this->put(route('settings.roles.update', $sales), ['name' => 'Renamed', 'permissions' => ['pos.sell', 'pos.returns.manage']])->assertSessionHasNoErrors();
        $this->assertSame('Sales person', $sales->fresh()->name);
        $this->assertContains('pos.returns.manage', $sales->fresh()->permissionKeys());
        $this->delete(route('settings.roles.destroy', $sales))->assertSessionHas('error');
    }

    public function test_a_role_with_members_cannot_be_archived(): void
    {
        $role = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Clerk']);
        $clerk = User::create(['name' => 'Clerk', 'email' => 'clerk@example.test', 'password' => bcrypt('x')]);
        $clerk->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $role->id]);

        $this->delete(route('settings.roles.destroy', $role))->assertSessionHas('error');
        $clerk->businesses()->updateExistingPivot($this->business->id, ['business_role_id' => null]);
        $this->delete(route('settings.roles.destroy', $role))->assertSessionHas('success');
        $this->assertSoftDeleted($role);
    }

    public function test_only_people_who_manage_users_can_edit_roles(): void
    {
        $role = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Buyer']);
        $role->syncPermissions(['stock.purchases.manage']);
        $buyer = User::create(['name' => 'Buyer', 'email' => 'buyer@example.test', 'password' => bcrypt('x')]);
        $buyer->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $role->id]);
        $this->enterBusiness($this->business, $buyer);

        $this->get(route('settings.roles'))->assertForbidden();
        $this->post(route('settings.roles.store'), ['name' => 'Hack', 'permissions' => ['core.users.manage']])->assertForbidden();
    }
}
