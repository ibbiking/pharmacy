<?php

namespace Tests\Feature\Platform;

use App\Jobs\SendBusinessMail;
use App\Mail\BusinessMessage;
use App\Models\AuditLog;
use App\Models\BusinessMailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4f: per-business email + branding. */
class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Rana Medical Store');
    }

    private function smtp(array $overrides = []): array
    {
        return array_merge([
            'driver' => 'smtp', 'from_name' => 'Rana Medical', 'from_address' => 'billing@rana.test', 'reply_to' => 'owner@rana.test',
            'smtp_host' => 'smtp.rana.test', 'smtp_port' => 587, 'smtp_encryption' => 'tls', 'smtp_username' => 'billing@rana.test', 'smtp_password' => 'p@ss-SECRET-123',
        ], $overrides);
    }

    public function test_only_the_owner_can_manage_email(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => bcrypt('x')]);
        $staff->businesses()->attach($this->business->id, ['role' => 'staff']);
        $this->enterBusiness($this->business, $staff);

        $this->get(route('settings.email'))->assertForbidden();
        $this->put(route('settings.email.update'), $this->smtp())->assertForbidden();
    }

    public function test_smtp_settings_are_validated_and_the_password_is_encrypted(): void
    {
        $this->put(route('settings.email.update'), ['driver' => 'smtp', 'from_address' => 'not-an-email', 'smtp_port' => 99999])
            ->assertSessionHasErrors(['from_address', 'smtp_host', 'smtp_port']);

        $this->put(route('settings.email.update'), $this->smtp())->assertSessionHasNoErrors();

        $raw = DB::table('business_mail_settings')->where('business_id', $this->business->id)->value('smtp_password');
        $this->assertNotSame('p@ss-SECRET-123', $raw);
        $this->assertSame('p@ss-SECRET-123', BusinessMailSetting::firstWhere('business_id', $this->business->id)->smtp_password);

        // Blank password keeps the saved one; the page never receives it.
        $this->put(route('settings.email.update'), $this->smtp(['smtp_password' => '', 'smtp_port' => 465]))->assertSessionHasNoErrors();
        $this->assertSame('p@ss-SECRET-123', BusinessMailSetting::firstWhere('business_id', $this->business->id)->smtp_password);

        $props = $this->get(route('settings.email'))->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Email')->where('settings.has_smtp_password', true)->where('settings.smtp_port', 465))
            ->viewData('page')['props'];
        $this->assertStringNotContainsString('p@ss-SECRET-123', json_encode($props));
        $this->assertStringNotContainsString('p@ss-SECRET-123', AuditLog::withoutGlobalScopes()->get()->toJson());
    }

    public function test_mailgun_needs_domain_and_key(): void
    {
        $this->put(route('settings.email.update'), ['driver' => 'mailgun', 'from_address' => 'a@b.test'])
            ->assertSessionHasErrors('mailgun_domain');
        $this->put(route('settings.email.update'), ['driver' => 'mailgun', 'from_address' => 'a@b.test', 'mailgun_domain' => 'mg.b.test'])
            ->assertSessionHasErrors('mailgun_secret');
        $this->put(route('settings.email.update'), ['driver' => 'mailgun', 'from_address' => 'a@b.test', 'mailgun_domain' => 'mg.b.test', 'mailgun_secret' => 'key-123', 'mailgun_endpoint' => 'api.eu.mailgun.net'])
            ->assertSessionHasNoErrors();
    }

    public function test_test_email_through_the_platform_mailer_is_recorded(): void
    {
        Mail::fake();

        $this->post(route('settings.email.test'), ['to' => 'me@example.test'])->assertSessionHas('success');

        Mail::assertSent(BusinessMessage::class, fn ($m) => $m->hasTo('me@example.test') && str_contains($m->mailSubject, 'Rana Medical Store'));
        $this->assertTrue((bool) BusinessMailSetting::firstWhere('business_id', $this->business->id)->last_test_ok);
    }

    public function test_a_failing_smtp_test_is_reported_without_leaking_the_password(): void
    {
        $this->put(route('settings.email.update'), $this->smtp(['smtp_host' => '127.0.0.1', 'smtp_port' => 1, 'smtp_encryption' => 'none']));

        $this->post(route('settings.email.test'), ['to' => 'me@example.test'])->assertSessionHas('error');

        $settings = BusinessMailSetting::firstWhere('business_id', $this->business->id);
        $this->assertFalse($settings->last_test_ok);
        $this->assertNotEmpty($settings->last_test_error);
        $this->assertStringNotContainsString('p@ss-SECRET-123', $settings->last_test_error);
    }

    public function test_preview_shows_the_branded_layout(): void
    {
        $html = $this->get(route('settings.email.preview'))->assertOk()->getContent();

        $this->assertStringContainsString('Rana Medical Store', $html);
        $this->assertStringContainsString('>RM<', $html, 'initials badge when there is no logo');
        $this->assertStringContainsString('#6d3ef0', $html, 'default brand colour');
    }

    public function test_logo_and_brand_colour(): void
    {
        Storage::fake('uploads');

        $this->post(route('settings.email.branding'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 120), 'brand_color' => '#12a150'])->assertSessionHasNoErrors();
        $business = $this->business->fresh();
        Storage::disk('uploads')->assertExists($business->logo);
        $this->assertSame('#12a150', $business->brand_color);
        $this->assertStringContainsString($business->logo, $this->get(route('settings.email.preview'))->getContent());

        $this->post(route('settings.email.branding'), ['brand_color' => 'red', 'logo' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml')])
            ->assertSessionHasErrors(['brand_color', 'logo']);

        $old = $business->logo;
        $this->post(route('settings.email.branding'), ['remove_logo' => true])->assertSessionHasNoErrors();
        Storage::disk('uploads')->assertMissing($old);
        $this->assertNull($this->business->fresh()->logo);
    }

    public function test_business_mail_is_queued_and_sent_as_the_business(): void
    {
        Mail::fake();

        SendBusinessMail::dispatch($this->business, 'customer@example.test', new BusinessMessage($this->business, 'Your invoice', 'Invoice INV-1', ['Thanks!']));

        Mail::assertSent(BusinessMessage::class, fn ($m) => $m->hasTo('customer@example.test') && $m->from[0]['address'] === config('mail.from.address') && $m->from[0]['name'] === 'Rana Medical Store');
    }
}
