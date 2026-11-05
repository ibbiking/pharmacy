<?php

namespace Tests\Feature\Platform;

use App\Mail\PlatformMessage;
use App\Models\Business;
use App\Models\SignupRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Roadmap 5.0: landing page → sign-up → email → password → business wizard; sign-in; forgot password. */
class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Role::findOrCreate('Business Owner');
        Mail::fake();
        RateLimiter::clear('login:owner@example.test|127.0.0.1');
    }

    private function user(string $email = 'owner@example.test', string $password = 'Secret123'): User
    {
        return User::create(['name' => 'Owner', 'email' => $email, 'password' => Hash::make($password)]);
    }

    private function sentLink(string $email): string
    {
        $url = null;
        Mail::assertSent(PlatformMessage::class, function (PlatformMessage $m) use ($email, &$url) {
            $url = $m->action[1] ?? null;

            return $m->hasTo($email);
        });

        return $url;
    }

    public function test_landing_page_shows_types_modules_and_calls_to_action(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome')
            ->has('types', 3)->where('types.0.key', 'pharmacy')->where('types.0.bundle_names', ['Stock & Purchasing', 'POS & Invoicing', 'Pharmacy Pack'])
            ->where('free', true)->where('signedIn', false)->has('modules'));

        $this->actingAs($this->user())->get('/')->assertInertia(fn (Assert $page) => $page->where('signedIn', true));
    }

    public function test_full_sign_up_journey_ends_in_the_wizard_with_the_type_preselected(): void
    {
        $this->get(route('signup', ['type' => 'inventory']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Signup')->where('preselectType', 'inventory')->has('types', 3));

        $this->post(route('signup'), ['business_type' => 'inventory', 'name' => 'Sara Khan', 'email' => 'Sara@Example.test'])
            ->assertRedirect(route('signup.sent', ['email' => 'sara@example.test']));
        $this->assertDatabaseHas('signup_requests', ['email' => 'sara@example.test', 'business_type' => 'inventory']);

        $this->get(route('signup.sent', ['email' => 'sara@example.test']))->assertInertia(fn (Assert $page) => $page->component('Auth/CheckEmail')->where('email', 'sara@example.test'));

        $link = $this->sentLink('sara@example.test');
        $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/SetPassword')->where('name', 'Sara Khan')->where('type', 'Inventory'));

        $token = SignupRequest::first()->token;
        $this->post(route('register.complete'), ['token' => $token, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post(route('register.complete'), ['token' => $token, 'password' => 'Abcdefgh1', 'password_confirmation' => 'Abcdefgh1'])
            ->assertRedirect(route('business.create', ['type' => 'inventory']));

        $user = User::where('email', 'sara@example.test')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Business Owner'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, SignupRequest::count(), 'the link works once');

        // Any other screen sends the new user to the wizard, which has the type preselected.
        $this->get(route('dashboard'))->assertRedirect();
        $this->get(route('business.create'))->assertInertia(fn (Assert $page) => $page->component('Business/Create')->where('preselectType', 'inventory'));
        $this->post(route('business.store'), ['name' => 'Sara Electronics', 'business_type' => 'inventory', 'modules' => ['pos']])->assertRedirect(route('dashboard'));
        $this->assertSame('inventory', Business::where('name', 'Sara Electronics')->value('business_type'));
        $this->assertNull(session('signup_business_type'));
    }

    public function test_sign_up_validation_and_existing_accounts(): void
    {
        $this->user('taken@example.test');

        $this->post(route('signup'), ['name' => 'X', 'email' => 'not-an-email'])->assertSessionHasErrors(['business_type', 'email', 'name']);
        $this->post(route('signup'), ['business_type' => 'pharmacy', 'name' => 'Taken', 'email' => 'TAKEN@example.test'])
            ->assertSessionHasErrors(['email' => 'An account with this email already exists. Sign in, or use “Forgot password”.']);
        $this->post(route('signup'), ['business_type' => 'spaceship', 'name' => 'Bad', 'email' => 'bad@example.test'])->assertSessionHasErrors('business_type');
        Mail::assertNothingSent();
    }

    public function test_bad_expired_and_reused_links(): void
    {
        $this->get(route('verification.verify', ['token' => 'nope']))->assertInertia(fn (Assert $page) => $page->component('Auth/LinkProblem')->where('problem', 'invalid'));

        SignupRequest::create(['name' => 'Old', 'email' => 'old@example.test', 'business_type' => 'pharmacy', 'token' => str_repeat('a', 64), 'expires_at' => now()->subMinute()]);
        $this->get(route('verification.verify', ['token' => str_repeat('a', 64)]))->assertInertia(fn (Assert $page) => $page->where('problem', 'expired'));
        $this->post(route('register.complete'), ['token' => str_repeat('a', 64), 'password' => 'Abcdefgh1', 'password_confirmation' => 'Abcdefgh1'])
            ->assertInertia(fn (Assert $page) => $page->component('Auth/LinkProblem'));
        $this->assertSame(0, User::where('email', 'old@example.test')->count());
    }

    public function test_resend_has_a_cooldown_and_replaces_the_link(): void
    {
        $this->post(route('signup'), ['business_type' => 'pharmacy', 'name' => 'Ali', 'email' => 'ali@example.test']);
        $first = SignupRequest::first()->token;

        $this->post(route('signup.resend'), ['email' => 'ali@example.test'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Please wait'));

        $this->travel(61)->seconds();
        $this->post(route('signup.resend'), ['email' => 'ali@example.test'])->assertSessionHas('success');
        $this->assertNotSame($first, SignupRequest::first()->token);
        $this->get(route('verification.verify', ['token' => $first]))->assertInertia(fn (Assert $page) => $page->where('problem', 'invalid'));
    }

    public function test_a_mail_failure_is_explained_instead_of_crashing(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->from(route('signup'))->post(route('signup'), ['business_type' => 'pharmacy', 'name' => 'Ali', 'email' => 'ali@example.test'])
            ->assertRedirect(route('signup'))->assertSessionHas('error', fn ($m) => str_contains($m, 'couldn’t send the email'));
    }

    public function test_an_account_created_meanwhile_is_not_duplicated(): void
    {
        SignupRequest::create(['name' => 'Twin', 'email' => 'twin@example.test', 'business_type' => 'pharmacy', 'token' => str_repeat('b', 64), 'expires_at' => now()->addHour()]);
        $this->user('twin@example.test');

        $this->post(route('register.complete'), ['token' => str_repeat('b', 64), 'password' => 'Abcdefgh1', 'password_confirmation' => 'Abcdefgh1'])
            ->assertRedirect(route('login', ['email' => 'twin@example.test']));
        $this->assertSame(1, User::where('email', 'twin@example.test')->count());
        $this->assertGuest();
    }

    public function test_login_success_failure_and_throttle(): void
    {
        $user = $this->user();
        $this->get(route('login', ['email' => 'owner@example.test']))->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('email', 'owner@example.test'));

        $this->post(route('login'), ['email' => 'owner@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => 'owner@example.test', 'password' => 'Secret123', 'remember' => true])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();

        foreach (range(1, 5) as $i) {
            $this->post(route('login'), ['email' => 'owner@example.test', 'password' => 'wrong']);
        }
        $this->post(route('login'), ['email' => 'owner@example.test', 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many sign-in attempts', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_legacy_register_is_retired(): void
    {
        $this->get('/admin/register')->assertRedirect(route('signup'));
        $this->post('/admin/register', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'a', 'password_confirmation' => 'a'])->assertRedirect(route('signup'));
        $this->assertSame(0, User::count());
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_forgot_password_full_flow(): void
    {
        $this->user();

        $this->post(route('password.email'), ['email' => 'owner@example.test'])->assertRedirect(route('password.verify.form', ['email' => 'owner@example.test']));
        $code = null;
        Mail::assertSent(PlatformMessage::class, function (PlatformMessage $m) use (&$code) {
            $code = $m->summary[0][1];

            return true;
        });

        $this->get(route('password.verify.form', ['email' => 'owner@example.test']))->assertInertia(fn (Assert $page) => $page->component('Auth/VerifyCode')->where('resendIn', fn ($s) => $s > 0));
        $this->get(route('password.reset.form'))->assertRedirect(route('password.request'));

        $this->post(route('password.verify'), ['email' => 'owner@example.test', 'code' => $code])->assertRedirect(route('password.reset.form'));
        $this->get(route('password.reset.form'))->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword')->where('email', 'owner@example.test'));
        $this->post(route('password.update'), ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect(route('login', ['email' => 'owner@example.test']))->assertSessionHas('status');

        $this->post(route('login'), ['email' => 'owner@example.test', 'password' => 'NewPass123'])->assertRedirect(route('dashboard'));
        $this->assertSame(0, DB::table('password_resets')->whereNull('deleted_at')->count(), 'the code cannot be used again');
    }

    public function test_reset_codes_die_after_five_wrong_tries_and_unknown_emails_look_the_same(): void
    {
        $this->user();
        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertRedirect(route('password.verify.form', ['email' => 'nobody@example.test']))->assertSessionHas('success');
        Mail::assertNothingSent();

        $this->post(route('password.email'), ['email' => 'owner@example.test']);
        foreach (range(1, 4) as $i) {
            $this->post(route('password.verify'), ['email' => 'owner@example.test', 'code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post(route('password.verify'), ['email' => 'owner@example.test', 'code' => '000000'])
            ->assertSessionHasErrors(['code' => 'Too many wrong codes. This code is cancelled: ask for a new one.']);
        $this->assertSame(0, DB::table('password_resets')->whereNull('deleted_at')->count());
    }

    public function test_reset_session_expires(): void
    {
        $this->user();
        $this->withSession(['password_reset_verified_email' => 'owner@example.test', 'password_reset_verified_at' => now()->subMinutes(20)->timestamp])
            ->post(route('password.update'), ['password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertRedirect(route('password.request'));
        $this->assertTrue(Hash::check('Secret123', User::first()->password));
    }

    public function test_signed_in_users_skip_guest_screens(): void
    {
        $this->actingAs($this->user());
        $this->get(route('login'))->assertRedirect('/admin/dashboard');
        $this->get(route('signup'))->assertRedirect('/admin/dashboard');
    }
}
