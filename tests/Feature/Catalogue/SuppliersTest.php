<?php

namespace Tests\Feature\Catalogue;

use App\Models\Business;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5: suppliers list + profile (Inertia). */
class SuppliersTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    private function stocked(): Purchase
    {
        [$product, $unit] = $this->makeProduct('Augmentin', ['Box' => 1, 'Tablet' => 10]);

        $this->purchase($product, $unit['Box'], 4, 250, 400, now()->addYear()->toDateString(), batch: 'B-77');

        return Purchase::query()->latest('id')->firstOrFail();
    }

    public function test_list_shows_totals_and_filters(): void
    {
        $this->stocked();
        Supplier::create(['name' => 'Idle Traders']);

        $this->get(route('suppliers.index', ['sort' => 'purchased_total', 'dir' => 'desc']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Suppliers/Index')
            ->where('rows.data.0.name', 'Main Supplier')->where('rows.data.0.purchases', 1)->where('rows.data.0.purchased_total', 1000)
            ->where('stats.total', 2)->where('stats.active', 1)->where('canManage', true));
        $this->get(route('suppliers.index', ['status' => 'idle']))->assertInertia(fn (Assert $page) => $page->where('rows.total', 1)->where('rows.data.0.name', 'Idle Traders'));
        $this->get(route('suppliers.index', ['q' => 'idle']))->assertInertia(fn (Assert $page) => $page->where('rows.total', 1));
    }

    public function test_profile_shows_history_chart_and_kpis(): void
    {
        $this->stocked();

        $this->get(route('suppliers.show', $this->supplier->id))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Suppliers/Show')
            ->where('supplier.name', 'Main Supplier')->where('supplier.products_count', 1)->where('supplier.purchases', 1)
            ->where('purchases.data.0.product', 'Augmentin')->where('purchases.data.0.batch', 'B-77')->where('purchases.data.0.total', 1000)
            ->has('months', 12)->where('months.11.total', 1000));
        $this->get(route('suppliers.show', [$this->supplier->id, 'q' => 'nothing']))->assertInertia(fn (Assert $page) => $page->where('purchases.total', 0));
    }

    public function test_validation_uniqueness_per_business_and_old_links(): void
    {
        $this->post(route('suppliers.store'), ['name' => 'Main Supplier'])->assertSessionHasErrors(['name' => 'You already have a supplier with this name.']);
        $this->post(route('suppliers.store'), ['name' => str_repeat('A', 30), 'phone' => 'call me', 'email' => 'nope'])->assertSessionHasErrors(['phone', 'email']);
        $this->post(route('suppliers.store'), ['name' => 'Long Name Medical Distributors Pvt Ltd', 'phone' => '+92 (300) 123-4567', 'email' => 'sales@example.test'])->assertSessionHas('success');

        $other = Business::create(['name' => 'Other']);
        $this->owner->businesses()->attach($other->id, ['role' => 'owner']);
        app()->forgetScopedInstances();
        $this->enterBusiness($other, $this->owner->fresh());
        $this->post(route('suppliers.store'), ['name' => 'Main Supplier'])->assertSessionHas('success');

        $this->get(route('suppliers.create'))->assertRedirect(route('suppliers.index', ['open' => 'create']));
        $this->get(route('suppliers.edit', 5))->assertRedirect(route('suppliers.index', ['open' => 5]));
    }

    public function test_archive_keeps_history_and_restore(): void
    {
        $purchase = $this->stocked();

        $this->delete(route('suppliers.destroy', $this->supplier->id))->assertSessionHas('success');
        $this->assertSoftDeleted($this->supplier);
        $this->assertSame('Main Supplier', $purchase->fresh()->supplier->name, 'history keeps the archived supplier');
        $this->get(route('suppliers.show', $this->supplier->id))->assertOk()->assertInertia(fn (Assert $page) => $page->where('supplier.archived', true));
        $this->getJson(route('suppliers.autocomplete', ['term' => 'Main']))->assertJsonCount(0, 'results');

        $this->post(route('suppliers.store'), ['name' => 'Main Supplier'])->assertSessionHas('success');
        $this->post(route('suppliers.restore', $this->supplier->id))->assertSessionHas('error');
        Supplier::where('name', 'Main Supplier')->first()->delete();
        $this->post(route('suppliers.restore', $this->supplier->id))->assertSessionHas('success');
    }

    public function test_suppliers_of_other_businesses_are_invisible(): void
    {
        $other = Business::create(['name' => 'Other']);
        $foreign = Supplier::withoutGlobalScopes()->forceCreate(['name' => 'Foreign', 'business_id' => $other->id]);
        $this->assertSame($other->id, (int) $foreign->business_id);

        $this->get(route('suppliers.show', $foreign->id))->assertNotFound();
        $this->put(route('suppliers.update', $foreign->id), ['name' => 'Hacked'])->assertNotFound();
        $this->delete(route('suppliers.destroy', $foreign->id))->assertNotFound();
    }
}
