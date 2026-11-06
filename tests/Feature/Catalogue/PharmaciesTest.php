<?php

namespace Tests\Feature\Catalogue;

use App\Models\Business;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Pharmacies screen (Inertia, Pharmacy Pack). */
class PharmaciesTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Alpha');
    }

    public function test_add_edit_archive_and_restore_within_the_business(): void
    {
        $this->post(route('pharmacies.store'), ['name' => 'Main Road', 'phone' => '0300'])->assertSessionHasNoErrors();
        $this->post(route('pharmacies.store'), ['name' => 'Main Road'])->assertSessionHasErrors('name');
        $p = Pharmacy::where('name', 'Main Road')->firstOrFail();
        $this->assertSame($this->business->id, (int) $p->business_id);

        $this->put(route('pharmacies.update', $p->id), ['name' => 'Main Road branch', 'address' => 'Lahore'])->assertSessionHasNoErrors();
        $this->get(route('pharmacies.index', ['q' => 'lahore']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Pharmacies')->where('rows.total', 1)->where('rows.data.0.name', 'Main Road branch')->where('stats.active', 1));

        $this->delete(route('pharmacies.destroy', $p->id))->assertSessionHas('success');
        $this->assertSoftDeleted($p);
        $this->post(route('pharmacies.restore', $p->id))->assertSessionHas('success');
        $this->assertNotSoftDeleted($p);

        $this->get('/admin/pharmacies/create')->assertRedirect(route('pharmacies.index', ['open' => 'create']));
    }

    public function test_other_businesses_pharmacies_are_not_found(): void
    {
        $beta = Business::create(['name' => 'Beta']);
        $theirs = Pharmacy::withoutGlobalScopes()->forceCreate(['name' => 'Theirs', 'business_id' => $beta->id]);

        $this->put(route('pharmacies.update', $theirs->id), ['name' => 'Mine'])->assertNotFound();
        $this->delete(route('pharmacies.destroy', $theirs->id))->assertNotFound();
        $this->assertSame('Theirs', Pharmacy::withoutGlobalScopes()->find($theirs->id)->name);
    }

    public function test_menu_item_only_for_businesses_with_the_pharmacy_pack(): void
    {
        $has = fn ($groups) => collect($groups)->pluck('items')->flatten(1)->contains('label', 'Pharmacies');
        $this->get(route('taxes.index'))->assertInertia(fn (Assert $page) => $page->where('navigation', $has));

        $this->business->update(['business_type' => 'inventory']);
        \App\Models\BusinessModule::query()->where('business_id', $this->business->id)->where('module', 'pharmacy')->delete();
        app()->forgetInstance(\App\Services\Modules\ModuleManager::class);
        $this->get(route('taxes.index'))->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($g) => ! $has($g)));
    }
}
