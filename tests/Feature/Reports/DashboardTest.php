<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.10: the owner's dashboard (deferred panels) and the sales-person redirect. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    public function test_dashboard_panels_and_suggestions(): void
    {
        $this->withoutVite();
        $this->setUpBusiness();
        [$p, $u] = $this->makeProduct('Tabs', ['Box' => 1, 'Tablet' => 10], ['Box' => [100, 80], 'Tablet' => [10, 8]]);
        $this->purchase($p, $u['Box'], 2, 80, 100, now()->addDays(20)->toDateString(), batch: 'SOON'); // 20 tablets, short-dated
        $p->update(['min_indicated_qty' => 3, 'min_qty_category_id' => $u['Box']]);                      // alert at 30 tablets → low
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 5, 10)]));        // 50 sales, cost 40
        [$noRule] = $this->makeProduct('No rule', ['Bottle' => 1]);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Dashboard')->where('filters.range', '30d')
            ->missing('kpis')
            ->loadDeferredProps(['kpis', 'charts', 'panels', 'suggestions'], fn (Assert $d) => $d
                ->where('kpis.net_sales.value', 50)->where('kpis.profit.value', 10)->where('kpis.invoices.value', 1)->where('kpis.invoices.change', null)
                ->where('kpis.stock_value.value', 120)                                  // 15 tablets × 8
                ->where('charts.top.0.product_id', $p->id)->where('charts.expiry.1.value', 120)
                ->where('panels.low.0.id', $p->id)->where('panels.invoices.0.invoice_no', $invoice->invoice_no)->where('panels.expiring.0.batch', 'SOON')
                ->where('suggestions.reorder.0.id', $p->id)->where('suggestions.short_dated', 1)->where('suggestions.no_alert', 1)));
        $this->assertNotNull($noRule);
    }

    public function test_sales_persons_go_to_the_pos(): void
    {
        $this->setUpBusiness();
        $cashier = User::create(['name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => bcrypt('x')]);
        $cashier->assignRole('sales-person');
        $cashier->businesses()->attach($this->business->id, ['role' => 'sales-person']);
        $this->enterBusiness($this->business, $cashier);

        $this->get(route('dashboard'))->assertRedirect(route('pos.index'));
    }
}
