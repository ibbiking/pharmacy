<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Currency\GlobalCurrency;
use App\Models\Preference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 5.11a: "My profile" and Business settings (Inertia). */
class SettingsScreensTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
    }

    public function test_profile_page_shows_only_the_signed_in_user(): void
    {
        $this->get(route('profile'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Account/Profile')
            ->where('account.email', $this->owner->email)
            ->where('account.email_locked', false));
    }

    public function test_u1_profile_update_never_touches_another_account(): void
    {
        $victim = User::create(['name' => 'Victim', 'email' => 'victim@example.test', 'password' => bcrypt('x')]);

        // The old route took a user id; it is gone. Posting to the new route only ever changes the caller.
        $this->post('/admin/profile/'.$victim->id, ['name' => 'Hacked', 'email' => 'me@example.test'])->assertNotFound();
        $this->post(route('profile.update'), ['name' => 'Owner Renamed', 'email' => $this->owner->email])->assertSessionHasNoErrors();

        $this->assertSame('Owner Renamed', $this->owner->fresh()->name);
        $this->assertSame('victim@example.test', $victim->fresh()->email);
        $this->assertSame('Victim', $victim->fresh()->name);
    }

    public function test_changing_email_needs_the_current_password_and_a_free_address(): void
    {
        User::create(['name' => 'Other', 'email' => 'taken@example.test', 'password' => bcrypt('x')]);
        $archived = User::create(['name' => 'Gone', 'email' => 'archived@example.test', 'password' => bcrypt('x')]);
        $archived->delete();

        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => 'new@example.test'])->assertSessionHasErrors('current_password');
        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => 'new@example.test', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => 'taken@example.test', 'current_password' => 'secret-password'])->assertSessionHasErrors('email');
        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => 'archived@example.test', 'current_password' => 'secret-password'])->assertSessionHasErrors('email');

        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => 'new@example.test', 'current_password' => 'secret-password'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.test', $this->owner->fresh()->email);
    }

    public function test_avatar_upload_is_an_image_up_to_2_mb(): void
    {
        Storage::fake('uploads');

        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => $this->owner->email, 'avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('avatar');
        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => $this->owner->email, 'avatar' => UploadedFile::fake()->image('big.jpg')->size(3000)])
            ->assertSessionHasErrors('avatar');

        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => $this->owner->email, 'avatar' => UploadedFile::fake()->image('me.png', 80, 80)])
            ->assertSessionHasNoErrors();
        $avatar = $this->owner->fresh()->avatar;
        $this->assertNotNull($avatar);
        Storage::disk('uploads')->assertExists('users/'.$avatar);

        $this->post(route('profile.update'), ['name' => 'Owner', 'email' => $this->owner->email, 'remove_avatar' => true])->assertSessionHasNoErrors();
        $this->assertNull($this->owner->fresh()->avatar);
    }

    public function test_sales_person_cannot_change_their_email(): void
    {
        $role = BusinessRole::where('business_id', $this->business->id)->where('system_key', 'sales-person')->first();
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => bcrypt('secret-password')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person', 'business_role_id' => $role?->id]);
        $this->enterBusiness($this->business, $cashier);

        $this->get(route('profile'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('account.email_locked', true));
        $this->post(route('profile.update'), ['name' => 'Cashier Two', 'email' => 'mine@example.test', 'current_password' => 'secret-password'])
            ->assertSessionHasErrors('email');
        $this->post(route('profile.update'), ['name' => 'Cashier Two', 'email' => 'cashier@example.test'])->assertSessionHasNoErrors();
        $this->assertSame('Cashier Two', $cashier->fresh()->name);
        $this->assertSame('cashier@example.test', $cashier->fresh()->email);
    }

    public function test_password_change_checks_current_password_and_strength_and_keeps_you_signed_in(): void
    {
        $this->put(route('update-password'), ['current_password' => 'wrong', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('update-password'), ['current_password' => 'secret-password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->put(route('update-password'), ['current_password' => 'secret-password', 'password' => 'NewPass123', 'password_confirmation' => 'Other123'])
            ->assertSessionHasErrors('password');

        $this->put(route('update-password'), ['current_password' => 'secret-password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPass123', $this->owner->fresh()->password));
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_business_settings_page_and_save(): void
    {
        $pkr = GlobalCurrency::create(['currency_code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs']);

        $this->get(route('business.settings'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Business')
            ->where('settings.name', 'Alpha')
            ->where('settings.invoice_source', 'local'));

        $this->post(route('business.settings.update'), [
            'name' => 'Alpha Pharmacy', 'phone' => '0300', 'address' => 'Main road', 'note' => 'Thanks!',
            'currency_id' => $pkr->id, 'global_min_indicated_qty' => 5,
        ])->assertSessionHasNoErrors();

        $b = $this->business->fresh();
        $this->assertSame(['Alpha Pharmacy', '0300', 'Main road', 'Thanks!', $pkr->id], [$b->name, $b->phone, $b->address, $b->note, $b->currency_id]);
        $this->assertEquals(5, Preference::where('business_id', $b->id)->where('slug', 'global_min_indicated_qty')->value('preference'));

        // Clearing the alert level switches it off; setting it again reuses the same row.
        $this->post(route('business.settings.update'), ['name' => 'Alpha Pharmacy'])->assertSessionHasNoErrors();
        $this->assertNull(Preference::where('business_id', $b->id)->where('slug', 'global_min_indicated_qty')->first());
        $this->post(route('business.settings.update'), ['name' => 'Alpha Pharmacy', 'global_min_indicated_qty' => 3])->assertSessionHasNoErrors();
        $this->assertSame(1, Preference::withTrashed()->where('business_id', $b->id)->where('slug', 'global_min_indicated_qty')->count());
    }

    public function test_b1_fbr_invoicing_is_refused_until_the_integration_exists(): void
    {
        $this->post(route('business.settings.update'), ['name' => 'Alpha', 'invoice_source' => 'fbr', 'fbr_ntn' => '1', 'fbr_pos_registration_no' => '2'])
            ->assertSessionHasErrors('invoice_source');
        $this->assertNotSame('fbr', $this->business->fresh()->invoice_source);
    }

    public function test_currency_picker_pages_and_hides_other_businesses_currencies(): void
    {
        foreach (range(1, 25) as $i) {
            GlobalCurrency::create(['currency_code' => sprintf('C%02d', $i), 'name' => "Currency $i", 'symbol' => '¤']);
        }
        $other = Business::create(['name' => 'Beta']);
        $foreign = GlobalCurrency::create(['business_id' => $other->id, 'currency_code' => 'BET', 'name' => 'Beta coin', 'symbol' => 'B']);
        $own = GlobalCurrency::create(['business_id' => $this->business->id, 'currency_code' => 'ALP', 'name' => 'Alpha coin', 'symbol' => 'A']);

        $first = $this->getJson(route('business.settings.currencies'))->assertOk();
        $this->assertCount(20, $first->json('data'));
        $this->assertSame(2, $first->json('next_page'));
        $this->assertSame(26, $first->json('total'));

        $this->assertSame([$own->id], collect($this->getJson(route('business.settings.currencies', ['q' => 'coin']))->json('data'))->pluck('id')->all());

        $this->post(route('business.settings.update'), ['name' => 'Alpha', 'currency_id' => $foreign->id])->assertSessionHasErrors('currency_id');
        $this->post(route('business.settings.update'), ['name' => 'Alpha', 'currency_id' => $own->id])->assertSessionHasNoErrors();
    }
}
