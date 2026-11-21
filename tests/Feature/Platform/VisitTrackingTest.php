<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Support\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Visitors per day (anonymous browser id), sign-ins, and the super admin's Visits page. */
class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_a_browser_is_counted_once_a_day_and_its_pages_add_up(): void
    {
        $first = $this->withHeader('User-Agent', self::CHROME)->get(route('home'))->assertOk();
        $cookie = collect($first->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === Visitor::COOKIE);
        $this->assertNotNull($cookie, 'the anonymous browser id is set');
        $id = decrypt($cookie->getValue(), false);
        $id = str_contains($id, '|') ? explode('|', $id, 2)[1] : $id;

        $this->withCookie(Visitor::COOKIE, $id)->withHeader('User-Agent', self::CHROME)->get(route('guide'))->assertOk();

        $this->assertSame(1, DB::table('site_visits')->count());
        $row = DB::table('site_visits')->first();
        $this->assertSame(2, (int) $row->hits);
        $this->assertSame(['Chrome', 'Windows', 'desktop'], [$row->browser, $row->os, $row->device]);
    }

    public function test_bots_are_marked_and_left_out_of_the_counts(): void
    {
        $this->assertSame('bot', Visitor::agent('Googlebot/2.1 (+http://www.google.com/bot.html)')['device']);
        $this->assertSame(['Safari', 'iOS', 'mobile'], array_values(Visitor::agent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1')));
    }

    public function test_every_sign_in_is_logged_and_the_super_admin_sees_the_visits(): void
    {
        Role::findOrCreate('super-admin');
        $root = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('secret-password1'), 'email_verified_at' => now()]);
        $root->assignRole('super-admin');

        $this->withHeader('User-Agent', self::CHROME)->post(route('login'), ['email' => 'root@example.test', 'password' => 'secret-password1']);
        $this->assertSame(1, DB::table('login_events')->where('user_id', $root->id)->where('browser', 'Chrome')->count());

        $this->actingAs(\App\Models\Platform\PlatformAdmin::query()->create(['name' => 'Root', 'email' => 'root@platform.test', 'password' => 'Platform123']), 'platform');
        $this->app['auth']->shouldUse('web');
        $this->get(route('platform.visits'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Platform/Visits')->where('totals.logins', 1)->has('series', 30));
    }
}
