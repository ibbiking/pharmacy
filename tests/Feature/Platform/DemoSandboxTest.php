<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\User;
use App\Services\Demo\DemoSandboxService;
use App\Support\Visitor;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Demo installation: one public demo login, a private copy of the demo businesses per browser, a guard on people /
 * roles / modules / settings, and the hourly cleanup that deletes idle copies with what the visitor added.
 */
class DemoSandboxTest extends TestCase
{
    use RefreshDatabase;

    private const A = '11111111-1111-4111-8111-111111111111';
    private const B = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['demo.instance' => true, 'demo.enabled' => true, 'demo.password' => 'Demo!Pass2026', 'demo.idle_minutes' => 60]);
        $this->seed(\Database\Seeders\GlobalAllCurrencySeeder::class);
    }

    private function signInAs(string $visitor, string $email = 'demo.owner@example.test'): User
    {
        Auth::logout();
        $this->flushSession();
        $this->withCookie(Visitor::COOKIE, $visitor)->post(route('login'), ['email' => $email, 'password' => 'Demo!Pass2026'])->assertRedirect();

        return Auth::user();
    }

    public function test_each_browser_gets_its_own_copy_and_keeps_it(): void
    {
        $service = app(DemoSandboxService::class);
        $service->build();
        $service->build();

        $a = $this->signInAs(self::A);
        $b = $this->signInAs(self::B);
        $this->assertNotSame($a->id, $b->id);
        $this->assertNotNull($a->demo_sandbox_id);
        $this->assertSame(3, $a->businesses()->count());
        $this->assertSame([], array_intersect($a->businesses()->pluck('businesses.id')->all(), $b->businesses()->pluck('businesses.id')->all()));

        // The same browser signing in again gets the same copy back.
        $this->assertSame($a->id, $this->signInAs(self::A)->id);
        // The cashier login of the same browser is the cashier of that same copy.
        $this->assertSame($a->demo_sandbox_id, $this->signInAs(self::A, 'demo.cashier@example.test')->demo_sandbox_id);
    }

    public function test_the_sign_in_page_fills_in_the_demo_login(): void
    {
        $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/Login')
            ->where('demo.password', 'Demo!Pass2026')->where('demo.logins.0.email', 'demo.owner@example.test')->where('demo.logins.2.role', 'Cashier'));

        config(['demo.instance' => false]);
        $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('demo', null));
    }

    public function test_a_demo_login_cannot_touch_people_roles_modules_or_settings(): void
    {
        app(DemoSandboxService::class)->build();
        $owner = $this->signInAs(self::A);
        $business = $owner->businesses()->where('business_type', 'pharmacy')->first();
        $this->withSession(['business_id' => $business->id]);

        $this->post(route('settings.team.invite'), ['email' => 'x@example.test', 'name' => 'X'])->assertSessionHas('error');
        $this->post(route('modules.deactivate', 'expense'))->assertSessionHas('error');
        $this->get(route('business.create'))->assertRedirect(route('dashboard'));
        $this->assertSame(3, DB::table('business_user')->where('business_id', $business->id)->count());
        $this->assertNotNull(DB::table('business_modules')->where('business_id', $business->id)->where('module', 'expense')->whereNull('deleted_at')->first());

        // Business data is theirs to try.
        $this->post(route('customers.store'), ['name' => 'Walk-in Test Customer'])->assertSessionDoesntHaveErrors();
    }

    public function test_cleanup_deletes_idle_copies_with_their_data_and_keeps_the_rest(): void
    {
        $this->seed(DemoDataSeeder::class); // the seeded original (public demo owner)
        $service = app(DemoSandboxService::class);
        $service->build();
        $service->build();
        $idle = $this->signInAs(self::A);
        $active = $this->signInAs(self::B);
        $idleBusiness = $idle->businesses()->first()->id;
        DB::table('customers')->insert(['business_id' => $idleBusiness, 'name' => 'Added by the visitor', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('demo_sandboxes')->where('id', $idle->demo_sandbox_id)->update(['last_seen_at' => now()->subMinutes(61)]);

        $this->assertSame(1, $service->cleanup());

        $this->assertNull(User::find($idle->id));
        $this->assertFalse(Business::withoutGlobalScopes()->whereKey($idleBusiness)->exists());
        $this->assertSame(0, DB::table('customers')->where('business_id', $idleBusiness)->count());
        $this->assertSame(0, DB::table('invoices')->where('business_id', $idleBusiness)->count());
        $this->assertNotNull(User::find($active->id), 'a copy in use stays');
        $this->assertSame(3, User::where('email', DemoDataSeeder::OWNER)->firstOrFail()->businesses()->count(), 'the seeded original stays');
    }

    public function test_copies_are_never_deleted_outside_a_demo_installation(): void
    {
        config(['demo.instance' => false]);
        $this->expectException(\RuntimeException::class);
        app(DemoSandboxService::class)->destroy(1);
    }
}
