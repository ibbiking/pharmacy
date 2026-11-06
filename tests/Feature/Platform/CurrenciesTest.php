<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\Currency\GlobalCurrency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 5.11c: currencies list (global + own) and the retired legacy Sale screen. */
class CurrenciesTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private GlobalCurrency $pkr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
        $this->pkr = GlobalCurrency::create(['currency_code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'exchange_rate' => 1]);
        GlobalCurrency::create(['currency_code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1]);
        $this->business->update(['currency_id' => $this->pkr->id]);
    }

    public function test_list_shows_global_and_own_but_never_other_businesses_currencies(): void
    {
        $beta = Business::create(['name' => 'Beta']);
        GlobalCurrency::create(['business_id' => $beta->id, 'currency_code' => 'BET', 'name' => 'Beta coin', 'symbol' => 'B']);
        GlobalCurrency::create(['business_id' => $this->business->id, 'currency_code' => 'ALP', 'name' => 'Alpha coin', 'symbol' => 'A']);

        $this->get(route('currencies.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Currencies')
            ->where('currencies.total', 3)
            ->where('currencies.data.0.code', 'PKR')->where('currencies.data.0.current', true) // your currency first
            ->where('currencies.data.0.can_manage', false)
            ->where('stats.global', 2)->where('stats.own', 1)->where('stats.current.currency_code', 'PKR'));

        $this->get(route('currencies.index', ['scope' => 'own']))->assertInertia(fn (Assert $page) => $page
            ->where('currencies.total', 1)->where('currencies.data.0.code', 'ALP')->where('currencies.data.0.can_manage', true));
        $this->get(route('currencies.index', ['q' => 'coin']))->assertInertia(fn (Assert $page) => $page->where('currencies.total', 1));
    }

    public function test_a_business_adds_edits_and_archives_only_its_own(): void
    {
        $this->post(route('currencies.store'), ['currency_code' => 'usd', 'name' => 'Dollar', 'symbol' => '$'])->assertSessionHasErrors('currency_code'); // C-3
        $this->post(route('currencies.store'), ['currency_code' => 'aed', 'name' => 'UAE Dirham', 'symbol' => 'AED'])->assertSessionHasNoErrors();
        $aed = GlobalCurrency::where('currency_code', 'AED')->firstOrFail();
        $this->assertSame($this->business->id, (int) $aed->business_id);
        $this->post(route('currencies.store'), ['currency_code' => 'AED', 'name' => 'Again', 'symbol' => 'x'])->assertSessionHasErrors('currency_code');

        $this->put(route('currencies.update', $aed->id), ['currency_code' => 'AED', 'name' => 'Dirham', 'symbol' => 'د.إ', 'exchange_rate' => 76])->assertSessionHasNoErrors();
        $this->assertSame('Dirham', $aed->fresh()->name);

        // Global currencies are read-only for a business.
        $this->put(route('currencies.update', $this->pkr->id), ['currency_code' => 'PKR', 'name' => 'Hacked', 'symbol' => 'Rs'])->assertForbidden();
        $this->delete(route('currencies.destroy', $this->pkr->id))->assertForbidden();

        // C-2: the currency the business uses can't be archived.
        $this->business->update(['currency_id' => $aed->id]);
        $this->delete(route('currencies.destroy', $aed->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted($aed);
        $this->business->update(['currency_id' => $this->pkr->id]);
        $this->delete(route('currencies.destroy', $aed->id))->assertSessionHas('success');
        $this->assertSoftDeleted($aed);

        // Old links open the list with the form.
        $this->get('/admin/currencies/create')->assertRedirect(route('currencies.index', ['create' => 1]));
    }

    public function test_another_business_cannot_touch_my_currency(): void
    {
        $mine = GlobalCurrency::create(['business_id' => $this->business->id, 'currency_code' => 'ALP', 'name' => 'Alpha coin', 'symbol' => 'A']);
        $other = User::create(['name' => 'Beta owner', 'email' => 'beta@example.test', 'password' => bcrypt('x')]);
        $other->assignRole('Business Owner');
        $beta = Business::create(['name' => 'Beta']);
        $other->businesses()->attach($beta->id, ['role' => 'owner']);
        $this->enterBusiness($beta, $other);

        $this->put(route('currencies.update', $mine->id), ['currency_code' => 'ALP', 'name' => 'Mine now', 'symbol' => 'A'])->assertForbidden();
        $this->delete(route('currencies.destroy', $mine->id))->assertForbidden();
        $this->assertSame('Alpha coin', $mine->fresh()->name);
    }

    public function test_super_admin_manages_global_currencies_but_not_ones_in_use(): void
    {
        Role::findOrCreate('super-admin');
        $root = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('x')]);
        $root->assignRole('super-admin');
        $this->actingAs($root)->withSession(['business_id' => null]);
        session()->forget('business_id');

        $this->get(route('currencies.index'))->assertInertia(fn (Assert $page) => $page->where('adding_global', true));
        $this->post(route('currencies.store'), ['currency_code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'])->assertSessionHasNoErrors();
        $this->assertNull(GlobalCurrency::where('currency_code', 'EUR')->value('business_id'));

        $this->put(route('currencies.update', $this->pkr->id), ['currency_code' => 'PKR', 'name' => 'Pakistan Rupee', 'symbol' => 'Rs'])->assertSessionHasNoErrors();
        $this->delete(route('currencies.destroy', $this->pkr->id))->assertSessionHas('error'); // Alpha uses it
        $this->assertNotSoftDeleted($this->pkr);
    }

    public function test_legacy_sale_screen_redirects_to_the_pos(): void
    {
        $this->get('/admin/sales')->assertRedirect(route('pos.index'));
        $this->get('/admin/sales/create')->assertRedirect(route('pos.index'));
        $this->post('/admin/sales', ['product' => 1, 'quantity' => 1])->assertStatus(405);
    }
}
