<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Preference;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 13: hardening (super admin account, per-business stock alerts, …). */
class HardeningTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private $product;
    private array $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
        [$this->product, $this->unit] = $this->makeProduct('Paracetamol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6]]);
        $this->purchase($this->product, $this->unit['Box'], 1, 60, 100, now()->addYear()->toDateString()); // 10 tablets
        // Business-wide alert level: half a box (in the largest unit) = 5 tablets.
        Preference::create(['business_id' => $this->business->id, 'type' => 'business', 'slug' => 'global_min_indicated_qty', 'preference' => 0.5]);
    }

    private function sell(int $tablets): void
    {
        $this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], $tablets, 10)])->assertOk();
    }

    public function test_13b_env_credentials_no_longer_sign_anyone_in(): void
    {
        config(['app.env' => 'testing']);
        putenv('SUPERADMINEMAIL=root@example.test');
        putenv('SUPERADMINPASSWORD=Secret123');
        auth()->logout();

        $this->post(route('login'), ['email' => 'root@example.test', 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNull(User::where('email', 'root@example.test')->first());
        putenv('SUPERADMINEMAIL');
        putenv('SUPERADMINPASSWORD');
    }

    public function test_13b_app_super_admin_command_creates_or_promotes(): void
    {
        $this->artisan('app:super-admin', ['email' => 'root@example.test', '--password' => 'weak'])->assertFailed();
        $this->artisan('app:super-admin', ['email' => 'root@example.test', '--password' => 'Strong123'])->assertSuccessful();
        $root = User::where('email', 'root@example.test')->firstOrFail();
        $this->assertTrue($root->hasRole('super-admin'));
        $this->assertTrue(Hash::check('Strong123', $root->password));

        $this->artisan('app:super-admin', ['email' => $this->owner->email, '--keep-password' => true])->assertSuccessful();
        $this->assertTrue($this->owner->fresh()->hasRole('super-admin'));
        $this->assertTrue(Hash::check('secret-password', $this->owner->fresh()->password));
    }

    public function test_13c_alert_only_when_a_sale_crosses_the_level_and_only_to_this_business(): void
    {
        Notification::fake();
        $salesRole = BusinessRole::where('business_id', $this->business->id)->where('system_key', 'sales-person')->first();
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => bcrypt('x')]);
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person', 'business_role_id' => $salesRole?->id]);
        $stranger = User::create(['name' => 'Other owner', 'email' => 'other@example.test', 'password' => bcrypt('x')]);
        $stranger->businesses()->attach(Business::create(['name' => 'Beta'])->id, ['role' => 'owner']);

        $this->sell(4); // 10 → 6, still above 5
        Notification::assertNothingSent();

        $this->sell(2); // 6 → 4: crosses
        Notification::assertSentTo($this->owner, StockAlertNotification::class, fn ($n) => $n->businessId === $this->business->id
            && $n->alerts[0]['name'] === 'Paracetamol' && $n->alerts[0]['stock'] == 0.4 && $n->alerts[0]['unit'] === 'Box');
        Notification::assertNotSentTo($stranger, StockAlertNotification::class);
        Notification::assertNotSentTo($cashier, StockAlertNotification::class); // can't see stock reports

        $this->sell(1); // already low: no repeat
        Notification::assertSentToTimes($this->owner, StockAlertNotification::class, 1);
    }

    public function test_13c_bell_shows_only_the_current_business(): void
    {
        $this->sell(6); // 10 → 4: alert stored for Alpha
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('unreadNotifications', 1));
        $list = $this->getJson(route('notifications.index'))->assertOk();
        $this->assertSame('Low stock: Paracetamol', $list->json('data.0.title'));

        // The same person in another business sees nothing there.
        $beta = Business::create(['name' => 'Beta']);
        $this->owner->businesses()->attach($beta->id, ['role' => 'owner']);
        $this->actingAs($this->owner->fresh()); // a real request loads the user (and its businesses) fresh
        $this->post(route('business.switch'), ['business_id' => $beta->id]);
        $this->getJson(route('notifications.index'))->assertJsonPath('unread', 0)->assertJsonCount(0, 'data');

        $this->post(route('business.switch'), ['business_id' => $this->business->id]);
        $this->postJson(route('notifications.read', $list->json('data.0.id')))->assertJsonPath('unread', 0);
    }
}
