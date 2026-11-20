<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Services\Access\PermissionService;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Super admin: a business page with every module, switches, and module / sub-module blocks that win over the owner. */
class AccessBlocksTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
        Role::findOrCreate('super-admin');
        $this->root = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('secret-password')]);
        $this->root->assignRole('super-admin');
    }

    private function modules(): ModuleManager
    {
        $m = app(ModuleManager::class);
        $m->forget();

        return $m;
    }

    private function asRoot(): void
    {
        $this->actingAs($this->root)->withSession([]);
        session()->forget(['business_id', 'impersonate_business_id']);
    }

    public function test_super_admin_sees_the_business_page_with_modules_and_sub_modules(): void
    {
        $this->asRoot();
        $this->get(route('superadmin.businesses.show', $this->business->id))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('SuperAdmin/BusinessShow')
                ->where('business.name', 'Alpha')
                ->where('modules', fn ($list) => collect($list)->firstWhere('key', 'pos') !== null
                    && count(collect($list)->firstWhere('key', 'pos')['subs']) > 0
                    && collect($list)->firstWhere('key', 'b2b_sales') === null)); // another type's module is not offered
    }

    public function test_owners_cannot_open_the_super_admin_page(): void
    {
        $this->get(route('superadmin.businesses.show', $this->business->id))->assertForbidden();
        $this->post(route('superadmin.businesses.block', $this->business->id), ['key' => 'pos', 'blocked' => true]);
        $this->assertFalse($this->modules()->isBlocked($this->business->id, 'pos'));
    }

    public function test_a_blocked_module_is_off_for_the_owner_and_cannot_be_switched_back_on(): void
    {
        $this->modules()->activate($this->business, 'expense', 'free', $this->owner->id);
        $this->asRoot();
        $this->post(route('superadmin.businesses.block', $this->business->id), ['key' => 'expense', 'blocked' => true])->assertSessionHas('success');

        $this->assertFalse($this->modules()->isActive($this->business->id, 'expense'));
        try {
            $this->modules()->activate($this->business, 'expense', 'free', $this->owner->id);
            $this->fail('a blocked module must not be switched on');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('blocked', $e->getMessage());
        }

        $this->post(route('superadmin.businesses.block', $this->business->id), ['key' => 'expense', 'blocked' => false]);
        $this->assertTrue($this->modules()->isActive($this->business->id, 'expense')); // its own switch was still on
    }

    public function test_a_blocked_sub_module_is_refused_even_to_the_owner(): void
    {
        $permissions = app(PermissionService::class);
        $this->assertTrue($permissions->allows($this->owner, $this->business->id, 'pos.returns.manage'));

        $this->asRoot();
        $this->post(route('superadmin.businesses.block', $this->business->id), ['key' => 'pos.returns.manage', 'blocked' => true])->assertSessionHas('success');
        $this->modules();
        $permissions->forget();
        $this->assertFalse($permissions->allows($this->owner, $this->business->id, 'pos.returns.manage'));
        $this->assertTrue($permissions->allows($this->owner, $this->business->id, 'pos.sell')); // the rest of the module still works
    }

    public function test_super_admin_switches_a_module_but_type_rules_still_apply(): void
    {
        $this->asRoot();
        $this->post(route('superadmin.businesses.module', [$this->business->id, 'analytics']), ['on' => true])->assertSessionHas('success');
        $this->assertTrue($this->modules()->isActive($this->business->id, 'analytics'));

        $this->post(route('superadmin.businesses.module', [$this->business->id, 'pharmacy']), ['on' => false])->assertSessionHas('error'); // part of the type
        $this->post(route('superadmin.businesses.module', [$this->business->id, 'b2b_sales']), ['on' => true])->assertSessionHas('error'); // another type
        $this->post(route('superadmin.businesses.block', $this->business->id), ['key' => 'core', 'blocked' => true])->assertSessionHas('error');
    }
}
