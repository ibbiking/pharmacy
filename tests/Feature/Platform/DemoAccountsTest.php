<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Demo accounts for client demos (config/demo.php, docs/DEPLOYMENT.md §12). */
class DemoAccountsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
    }

    public function test_demo_logins_keep_their_email_and_password(): void
    {
        $this->owner->forceFill(['email' => 'demo.owner@example.test', 'password' => Hash::make('secret-password')])->save();

        // DemoGuard refuses first (flash error); the Profile controller refuses too if it is ever reached.
        $this->post(route('profile.update'), ['name' => 'Renamed', 'email' => 'mine@example.test', 'current_password' => 'secret-password'])
            ->assertSessionHas('error');
        $this->put(route('update-password'), ['current_password' => 'secret-password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHas('error');

        $fresh = $this->owner->fresh();
        $this->assertSame('demo.owner@example.test', $fresh->email);
        $this->assertTrue(Hash::check('secret-password', $fresh->password));

        // The controller's own check, without the guard.
        $this->withoutMiddleware(\App\Http\Middleware\DemoGuard::class);
        $this->post(route('profile.update'), ['name' => 'Renamed', 'email' => 'mine@example.test', 'current_password' => 'secret-password'])
            ->assertSessionHasErrors('email');
        $this->assertSame('demo.owner@example.test', $this->owner->fresh()->email);
    }

    public function test_ordinary_accounts_are_not_locked(): void
    {
        $this->assertFalse($this->owner->isDemoAccount());
        $this->put(route('update-password'), ['current_password' => 'secret-password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasNoErrors();
    }

    public function test_rebuild_refuses_outside_a_demo_installation_and_touches_nothing(): void
    {
        config(['demo.instance' => false]);

        $this->assertSame(1, Artisan::call('demo:rebuild', ['--force' => true]));
        $this->assertStringContainsString('not a demo installation', Artisan::output());
        $this->assertTrue(Business::whereKey($this->business->id)->exists());
        $this->assertTrue(User::whereKey($this->owner->id)->exists());
    }

    public function test_the_seeder_needs_a_password_on_a_live_server(): void
    {
        $this->app['env'] = 'production';
        config(['demo.enabled' => true, 'demo.password' => null]);

        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);
        $this->assertFalse(User::where('email', 'demo.owner@example.test')->exists());

        config(['demo.enabled' => false, 'demo.password' => 'Live!Pass2026']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);
        $this->assertFalse(User::where('email', 'demo.owner@example.test')->exists());
    }
}
