<?php

namespace Tests\Feature\Platform;

use App\Models\Business;
use App\Models\Invoice;
use App\Models\NumberSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap step 4g: per-business document numbering. */
class NumberingTest extends TestCase
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
        $this->purchase($this->product, $this->unit['Box'], 5, 60, 100, now()->addYear()->toDateString());
    }

    private function sell(int $qty = 1): Invoice
    {
        return $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], $qty, 10)]));
    }

    public function test_each_business_numbers_its_own_invoices(): void
    {
        $this->assertSame('INV-000001', $this->sell()->invoice_no);
        $this->assertSame('INV-000002', $this->sell()->invoice_no);

        // A second business starts at 1 too (unique per business, not globally).
        $owner = User::create(['name' => 'Beta owner', 'email' => 'beta@example.test', 'password' => bcrypt('x')]);
        $beta = Business::create(['name' => 'Beta']);
        $owner->businesses()->attach($beta->id, ['role' => 'owner']);
        $this->enterBusiness($beta, $owner);
        [$p, $u] = $this->makeProduct('Ibuprofen', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6]]);
        $this->purchase($p, $u['Box'], 1, 60, 100, now()->addYear()->toDateString());
        $betaInvoice = $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 1, 10)]));

        $this->assertSame('INV-000001', $betaInvoice->invoice_no);
        $this->assertSame(2, Invoice::withoutGlobalScopes()->where('invoice_no', 'INV-000001')->count());
    }

    public function test_existing_numbers_are_skipped(): void
    {
        $first = $this->sell();
        NumberSeries::where('business_id', $this->business->id)->where('document_type', 'invoice')->update(['next_number' => 1]);

        $this->assertSame('INV-000001', $first->invoice_no);
        $this->getJson(route('pos.next-invoice-number'))->assertJsonPath('invoice_no', 'INV-000002'); // preview skips too
        $this->assertSame('INV-000002', $this->sell()->invoice_no);
    }

    public function test_returns_have_their_own_series(): void
    {
        $invoice = $this->sell(4);
        $item = $invoice->items->first();

        $this->post(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 1]);
        $this->post(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 1]);

        $this->assertSame(['RET-000001', 'RET-000002'], $item->returns()->orderBy('id')->pluck('return_no')->all());
    }

    public function test_pos_preview_matches_the_number_given(): void
    {
        $this->getJson(route('pos.next-invoice-number'))->assertJsonPath('invoice_no', 'INV-000001');
        $this->assertSame('INV-000001', $this->sell()->invoice_no);
        $this->getJson(route('pos.next-invoice-number'))->assertJsonPath('invoice_no', 'INV-000002');
    }

    public function test_owner_can_change_numbering_but_not_go_backwards(): void
    {
        $this->sell();
        $this->sell();

        $this->get(route('settings.numbering'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Numbering')->where('series.0.type', 'invoice')->where('series.0.highest_used', 2)->where('series.0.preview', 'INV-000003'));

        $this->put(route('settings.numbering.update', 'invoice'), ['prefix' => 'INV-', 'next_number' => 2, 'padding' => 6])->assertSessionHasErrors('next_number');
        $this->put(route('settings.numbering.update', 'invoice'), ['prefix' => 'S<1>', 'next_number' => 5, 'padding' => 6])->assertSessionHasErrors('prefix');

        // A new prefix starts fresh (nothing used with it yet).
        $this->put(route('settings.numbering.update', 'invoice'), ['prefix' => 'RMS/26/', 'next_number' => 1, 'padding' => 4])->assertSessionHasNoErrors();
        $this->assertSame('RMS/26/0001', $this->sell()->invoice_no);

        $this->put(route('settings.numbering.update', 'quotation'), ['prefix' => 'Q', 'next_number' => 1, 'padding' => 4])->assertNotFound();
    }

    public function test_only_the_owner_manages_numbering_and_new_businesses_get_series(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff2@example.test', 'password' => bcrypt('x')]);
        $staff->businesses()->attach($this->business->id, ['role' => 'staff']);
        $this->enterBusiness($this->business, $staff);
        $this->get(route('settings.numbering'))->assertForbidden();

        $new = Business::create(['name' => 'Gamma']);
        $this->assertEqualsCanonicalizing(['invoice', 'return'], NumberSeries::where('business_id', $new->id)->pluck('document_type')->all());
    }
}
