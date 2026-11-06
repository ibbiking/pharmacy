<?php

namespace Tests\Feature\Reports;

use App\Models\Preference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.9b: expiry and stock alerts read the real lot stock (RP-3, RP-4); purchase report = purchases list. */
class StockReportsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    public function test_expiry_lists_only_batches_with_stock_left(): void
    {
        [$p, $u] = $this->makeProduct('Tabs', ['Box' => 1, 'Tablet' => 10], ['Box' => [100, 80], 'Tablet' => [10, 8]]);
        $this->purchase($p, $u['Box'], 1, 80, 100, now()->addDays(20)->toDateString(), batch: 'SOON');
        $this->purchase($p, $u['Box'], 1, 80, 100, now()->subDays(3)->toDateString(), batch: 'GONE');
        $this->purchase($p, $u['Box'], 1, 80, 100, now()->addYear()->toDateString(), batch: 'LATE');
        // Sell the whole SOON batch: it must disappear from the report (RP-3).
        $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 10, 10)]));

        $this->get(route('reports.expiry'))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Reports/Expiry')
            ->where('rows.total', 2)->where('stats.expired.count', 1)->where('stats.expired.value', 80)->where('stats.30.count', 0)->where('stats.later.count', 1));
        $this->get(route('reports.expiry', ['status' => 'expired']))->assertInertia(fn (Assert $a) => $a->where('rows.data.0.batch', 'GONE')->where('rows.data.0.remaining', 10));
    }

    public function test_stock_alerts_use_sellable_stock_and_show_gaps(): void
    {
        [$low, $u] = $this->makeProduct('Low', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($low, $u['Box'], 1, 80, 100, now()->addYear()->toDateString());      // 10 sellable
        $this->purchase($low, $u['Box'], 5, 80, 100, now()->subDay()->toDateString());       // 50 expired: must not count (RP-4)
        $low->update(['min_indicated_qty' => 2, 'min_qty_category_id' => $u['Box']]);       // alert at 2 boxes = 20 tablets
        [$fine, $u2] = $this->makeProduct('Fine', ['Box' => 1, 'Tablet' => 10]);
        $this->purchase($fine, $u2['Box'], 5, 80, 100, now()->addYear()->toDateString());
        $fine->update(['min_indicated_qty' => 1, 'min_qty_category_id' => $u2['Box']]);
        [$none] = $this->makeProduct('No rule', ['Bottle' => 1]);

        $this->get(route('reports.stock_indication'))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Reports/StockAlerts')
            ->where('stats.low', 1)->where('stats.ok', 1)->where('rows.data.0.name', 'Low')->where('rows.data.0.stock', 1)->where('rows.data.0.unit', 'Box'));
        $this->get(route('reports.stock_unaligned'))->assertInertia(fn (Assert $a) => $a->component('Reports/StockGaps')->where('total', 1)->where('rows.data.0.name', 'No rule'));

        // A business-wide level (in the largest unit) covers products without their own.
        Preference::create(['business_id' => $this->business->id, 'type' => 'business', 'slug' => 'global_min_indicated_qty', 'preference' => '3']);
        $this->get(route('reports.stock_unaligned'))->assertInertia(fn (Assert $a) => $a->where('total', 0));
        $this->get(route('reports.stock_indication'))->assertInertia(fn (Assert $a) => $a->where('stats.low', 2)); // "No rule" has 0 stock now counts
        $this->assertNotNull($none);
    }

    public function test_purchase_report_is_the_purchases_list_with_dates(): void
    {
        [$p, $u] = $this->makeProduct('Tabs', ['Box' => 1]);
        $this->purchase($p, $u['Box'], 2, 80, 100, now()->addYear()->toDateString());
        $this->get(route('purchases.report', ['from_date' => today()->toDateString(), 'to_date' => today()->toDateString()]))
            ->assertRedirect(route('purchases.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]));
        $this->get(route('purchases.index', ['from' => today()->toDateString()]))->assertInertia(fn (Assert $a) => $a->where('period.count', 1)->where('period.spend', 160));
    }
}
