<?php

namespace Tests\Feature\PlatformPortal;

use App\Models\Business;
use App\Models\Platform\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PlatformPortal;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** The separate super admin portal at /platform (docs/SUPER_ADMIN_PORTAL.md). */
class PortalTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;
    use PlatformPortal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha Pharmacy', 'pharmacy');
    }

    public function test_sign_in_is_separate_and_gives_nothing_away(): void
    {
        auth('web')->logout();
        PlatformAdmin::query()->create(['name' => 'Root', 'email' => 'root@platform.test', 'password' => 'Platform123']);

        $this->get('/platform')->assertRedirect(route('platform.login'));
        $this->get(route('platform.login'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Platform/Login'));

        // Unknown email and wrong password get the same answer; a business login is not a platform login.
        $msg = 'These details don’t match a platform admin.';
        $this->post(route('platform.login.attempt'), ['email' => 'nobody@platform.test', 'password' => 'x'])->assertSessionHasErrors(['email' => $msg]);
        $this->post(route('platform.login.attempt'), ['email' => 'root@platform.test', 'password' => 'wrong'])->assertSessionHasErrors(['email' => $msg]);
        $this->post(route('platform.login.attempt'), ['email' => $this->owner->email, 'password' => 'password'])->assertSessionHasErrors(['email' => $msg]);

        $this->post(route('platform.login.attempt'), ['email' => 'ROOT@platform.test', 'password' => 'Platform123'])->assertRedirect(route('platform.dashboard'));
        $this->assertNotNull(auth('platform')->id());
        $this->assertNull(auth('web')->id(), 'signing in to the platform never signs in to a business');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'login']);

        $this->post(route('platform.logout'))->assertRedirect(route('platform.login'));
        $this->assertNull(auth('platform')->id());
    }

    public function test_sign_in_is_throttled_and_a_switched_off_admin_is_refused(): void
    {
        auth('web')->logout();
        PlatformAdmin::query()->create(['name' => 'Off', 'email' => 'off@platform.test', 'password' => 'Platform123', 'is_active' => false]);
        $this->post(route('platform.login.attempt'), ['email' => 'off@platform.test', 'password' => 'Platform123'])
            ->assertSessionHasErrors(['email' => 'This platform admin account is switched off.']);
        $this->assertNull(auth('platform')->id());

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('platform.login.attempt'), ['email' => 'off@platform.test', 'password' => 'nope']);
        }
        $this->post(route('platform.login.attempt'), ['email' => 'off@platform.test', 'password' => 'Platform123'])
            ->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
    }

    public function test_a_business_user_never_reaches_the_portal_and_a_platform_admin_never_reaches_a_business_without_opening_it(): void
    {
        foreach (['/platform', '/platform/businesses', '/platform/people', '/platform/billing', '/platform/admins', '/platform/settings', '/platform/backups', '/platform/visits'] as $url) {
            $this->get($url)->assertRedirect(route('platform.login'));
        }

        auth('web')->logout();
        $this->asPlatformAdmin();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('pos.index'))->assertRedirect(route('login'));
    }

    public function test_every_portal_screen_opens_for_a_platform_admin(): void
    {
        $this->asPlatformAdmin();
        $screens = [
            'platform.dashboard' => 'Platform/Dashboard', 'platform.businesses' => 'Platform/Businesses', 'platform.people' => 'Platform/Users',
            'platform.roles' => 'Platform/Roles', 'platform.visits' => 'Platform/Visits', 'platform.billing' => 'Platform/Billing',
            'platform.billing.subscriptions' => 'Platform/Subscriptions', 'platform.settings' => 'Platform/AppSettings',
            'platform.backups' => 'Platform/Backups', 'platform.admins' => 'Platform/Admins',
        ];
        foreach ($screens as $name => $component) {
            $this->get(route($name))->assertOk()->assertInertia(fn (Assert $p) => $p->component($component)
                ->where('admin.email', 'root@platform.test')->missing('business')->missing('navigation'));
        }
        $this->get(route('platform.businesses.show', $this->business->id))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Platform/BusinessShow')->where('business.name', 'Alpha Pharmacy'));
    }

    public function test_every_business_type_is_listed_and_opened_with_the_owners_full_rights(): void
    {
        $types = ['pharmacy' => $this->business];
        foreach (['inventory' => 'Beta Store', 'inventory_b2b' => 'Gamma Wholesale'] as $type => $name) {
            $b = Business::create(['name' => $name, 'business_type' => $type]);
            $owner = User::create(['name' => "$name Owner", 'email' => "$type-owner@example.test", 'password' => bcrypt('x')]);
            $owner->businesses()->attach($b->id, ['role' => 'owner']);
            $types[$type] = $b;
        }
        auth('web')->logout();
        $admin = $this->platformAdmin();
        $this->asPlatformAdmin($admin);

        $this->get(route('platform.dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('stats.businesses', 3)
            ->where('types', fn ($t) => collect($t)->pluck('count', 'key')->all() === ['pharmacy' => 1, 'inventory' => 1, 'inventory_b2b' => 1]));

        foreach ($types as $type => $business) {
            $this->get(route('platform.businesses', ['type' => $type]))->assertInertia(fn (Assert $p) => $p
                ->where('businesses.total', 1)->where('businesses.data.0.id', $business->id));

            $this->post(route('platform.businesses.open', $business->id))->assertRedirect(route('dashboard'));
            $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->where('business.id', $business->id)->where('business.type', $type)->where('business.impersonating', true));
            $this->get(route('settings.team'))->assertOk(); // owner-only screen: full rights
            $this->post(route('platform.leave'))->assertRedirect(route('platform.businesses.show', $business->id));
            $this->assertNull(auth('web')->id());
        }
        $this->assertSame(3, \DB::table('platform_audit_logs')->where('action', 'business.open')->where('platform_admin_id', $admin->id)->count());
    }

    public function test_opening_a_second_business_after_the_first_keeps_the_new_owner_signed_in(): void
    {
        // Found by the live health check: the previous owner's password fingerprint made AuthenticateSession sign the
        // next owner out (dashboard 302, then 419 on "Back to platform").
        $b = Business::create(['name' => 'Beta Store', 'business_type' => 'inventory']);
        $owner = User::create(['name' => 'Beta Owner', 'email' => 'beta-owner@example.test', 'password' => bcrypt('other-password')]);
        $owner->businesses()->attach($b->id, ['role' => 'owner']);
        auth('web')->logout();
        $this->asPlatformAdmin();

        $this->withSession(['password_hash_web' => $this->owner->password]); // left over from the first business
        $this->post(route('platform.businesses.open', $b->id))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('business.id', $b->id));
        $this->post(route('platform.leave'))->assertRedirect(route('platform.businesses.show', $b->id));
        $this->assertNull(session('password_hash_web'));
    }

    public function test_platform_admins_are_managed_but_nobody_switches_off_themselves(): void
    {
        $me = $this->platformAdmin();
        $this->asPlatformAdmin($me);

        $this->post(route('platform.admins.store'), ['name' => 'Second', 'email' => 'second@platform.test', 'password' => 'weak'])->assertSessionHasErrors('password');
        $this->post(route('platform.admins.store'), ['name' => 'Second', 'email' => 'second@platform.test', 'password' => 'Second123'])->assertSessionHas('success');
        $this->post(route('platform.admins.store'), ['name' => 'Again', 'email' => 'second@platform.test', 'password' => 'Second123'])->assertSessionHasErrors('email');
        $second = PlatformAdmin::query()->where('email', 'second@platform.test')->firstOrFail();

        $this->post(route('platform.admins.toggle', $me->id))->assertSessionHas('error');
        $this->delete(route('platform.admins.destroy', $me->id))->assertSessionHas('error');
        $this->assertTrue($me->fresh()->is_active);

        $this->post(route('platform.admins.toggle', $second->id))->assertSessionHas('success');
        $this->assertFalse($second->fresh()->is_active);
        $this->delete(route('platform.admins.destroy', $second->id))->assertSessionHas('success');
        $this->assertSoftDeleted($second);
    }

    public function test_the_console_command_creates_and_updates_a_platform_admin(): void
    {
        $this->artisan('platform:admin', ['email' => 'Boss@Platform.test', '--name' => 'Boss', '--password' => 'weak'])->assertFailed();
        $this->artisan('platform:admin', ['email' => 'Boss@Platform.test', '--name' => 'Boss', '--password' => 'Boss12345'])->assertSuccessful();
        $boss = PlatformAdmin::query()->where('email', 'boss@platform.test')->firstOrFail();
        $hash = $boss->password;
        $this->artisan('platform:admin', ['email' => 'boss@platform.test', '--password' => ''])->assertSuccessful(); // empty keeps it
        $this->assertSame($hash, $boss->fresh()->password);
        $this->assertSame(1, PlatformAdmin::query()->count());
    }
}
