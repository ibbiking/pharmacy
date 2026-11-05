<?php

namespace Tests\Feature\Platform;

use App\Jobs\SendBusinessMail;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 4i-3: team management. */
class TeamTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private BusinessRole $salesRole;
    private BusinessRole $ownerRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
        $this->salesRole = BusinessRole::where('business_id', $this->business->id)->where('system_key', 'sales-person')->first();
        $this->ownerRole = BusinessRole::where('business_id', $this->business->id)->where('system_key', 'owner')->first();
    }

    private function cashier(): User
    {
        $u = User::create(['name' => 'Cashier', 'email' => 'cashier-team@example.test', 'password' => bcrypt('x')]);
        $u->businesses()->attach($this->business->id, ['role' => 'sales-person', 'business_role_id' => $this->salesRole->id]);

        return $u;
    }

    public function test_team_page_lists_members_with_roles(): void
    {
        $this->cashier();

        $this->get(route('settings.team'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Team')
            ->where('members.total', 2)
            ->where('members.data.0.role_name', 'Owner')
            ->where('members.data.0.is_me', true)
            ->where('stats.active', 2)
            ->has('roles', 2));
    }

    public function test_inviting_a_new_person_creates_the_account_and_queues_the_email(): void
    {
        Queue::fake();

        $this->post(route('settings.team.invite'), ['name' => 'Ali', 'email' => 'ali@example.test', 'role_id' => $this->salesRole->id])->assertSessionHas('success');

        $ali = User::where('email', 'ali@example.test')->firstOrFail();
        $this->assertSame('sales-person', $ali->roleIn($this->business->id));
        Queue::assertPushed(SendBusinessMail::class, fn ($job) => $job->to === 'ali@example.test');

        $this->post(route('settings.team.invite'), ['name' => 'Ali', 'email' => 'ali@example.test', 'role_id' => $this->salesRole->id])->assertSessionHasErrors('email');
    }

    public function test_inviting_someone_from_another_business_adds_them(): void
    {
        Queue::fake();
        $other = User::create(['name' => 'Sara', 'email' => 'sara@example.test', 'password' => bcrypt('x')]);
        $other->businesses()->attach(Business::create(['name' => 'Elsewhere'])->id, ['role' => 'owner']);

        $this->post(route('settings.team.invite'), ['name' => 'Sara', 'email' => 'sara@example.test', 'role_id' => $this->ownerRole->id])->assertSessionHas('success');

        $this->assertSame(2, $other->businesses()->count());
        $this->assertSame('owner', $other->roleIn($this->business->id));
    }

    public function test_change_role_suspend_and_remove(): void
    {
        $cashier = $this->cashier();
        $manager = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Manager']);
        $manager->syncPermissions(['pos.sell', 'pos.returns.manage']);

        $this->put(route('settings.team.update', $cashier), ['role_id' => $manager->id])->assertSessionHas('success');
        $this->assertSame('staff', $cashier->roleIn($this->business->id));

        $this->put(route('settings.team.update', $cashier), ['status' => 'suspended'])->assertSessionHas('success');
        app()->forgetScopedInstances();
        $this->actingAs($cashier)->withSession(['business_id' => $this->business->id]);
        session(['business_id' => $this->business->id]);
        $this->assertFalse($cashier->can('pos.sell'), 'suspended');

        $this->actingAs($this->owner)->withSession(['business_id' => $this->business->id]);
        $this->delete(route('settings.team.remove', $cashier))->assertSessionHas('success');
        $this->assertNull($cashier->roleIn($this->business->id));
    }

    public function test_the_business_always_keeps_an_active_owner_and_you_cannot_change_yourself(): void
    {
        $this->put(route('settings.team.update', $this->owner), ['role_id' => $this->salesRole->id])->assertSessionHas('error');
        $this->delete(route('settings.team.remove', $this->owner))->assertSessionHas('error');

        // A second owner exists, but the remaining one still cannot be suspended by the other.
        $second = User::create(['name' => 'Partner', 'email' => 'partner@example.test', 'password' => bcrypt('x')]);
        $second->businesses()->attach($this->business->id, ['role' => 'owner', 'business_role_id' => $this->ownerRole->id]);
        $this->put(route('settings.team.update', $second), ['status' => 'suspended'])->assertSessionHas('success');
        $this->actingAs($second)->withSession(['business_id' => $this->business->id]);
        $this->put(route('settings.team.update', $this->owner), ['status' => 'suspended'])->assertForbidden(); // suspended owner cannot manage
    }

    public function test_only_managers_can_open_the_team(): void
    {
        $cashier = $this->cashier();
        $role = BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Clerk']);
        $cashier->businesses()->updateExistingPivot($this->business->id, ['role' => 'staff', 'business_role_id' => $role->id]);
        $this->enterBusiness($this->business, $cashier);

        $this->get(route('settings.team'))->assertForbidden();
    }
}
