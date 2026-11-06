<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.9a: sales, profit & loss and returns reports (RP-1, RP-2, RP-5) on server-side sums. */
class MoneyReportsTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private $product;
    private array $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
        [$this->product, $this->unit] = $this->makeProduct('Tabs', ['Box' => 1, 'Tablet' => 10], ['Box' => [100, 80], 'Tablet' => [10, 8]]);
        $this->purchase($this->product, $this->unit['Box'], 5, 80, 100, now()->addYear()->toDateString()); // 8 per tablet
    }

    public function test_sales_profit_and_returns_use_real_refunds_and_batch_costs(): void
    {
        // 3 tablets at 10 with an invoice discount of 3 → paid 27; cost 3 × 8 = 24.
        $invoice = $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 3, 10)],
            ['invoice_discount_type' => 'amount', 'invoice_discount_value' => 3, 'cash_received' => 50]));
        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 10)])); // 20, cost 16
        $item = $invoice->items->first();
        $this->postJson(route('invoices.return-product', [$invoice->invoice_no, $item->id]), ['return_qty' => 1])->assertOk(); // refund 10 − 1 = 9

        $this->get(route('reports.sales', ['range' => 'today']))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Reports/Sales')
            ->where('report.kpis.invoices', 2)->where('report.kpis.net_sales', 38)          // 18 + 20 after the return
            ->where('report.kpis.refunds', 9)->where('report.kpis.returns', 1)            // RP-1: the real refund, not qty × price
            ->where('best.data.0.product_id', $this->product->id)->has('report.series', 1));

        // Revenue 18 + 20 = 38; cost of what is still sold: 4 tablets × 8 = 32.
        $this->get(route('reports.profit_loss', ['range' => 'today']))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Reports/Profit')
            ->where('report.kpis.revenue', 38)->where('report.kpis.cost', 32)->where('report.kpis.profit', 6)->where('report.kpis.estimated_lines', 0)
            ->where('products.data.0.profit', 6));

        $this->get(route('reports.returns', ['range' => '7d']))->assertOk()->assertInertia(fn (Assert $a) => $a->component('Reports/Returns')
            ->where('kpis.refunds', 9)->where('rows.data.0.refund', 9)->where('rows.data.0.invoice_no', $invoice->invoice_no));

        // An empty period is fine.
        $this->get(route('reports.sales', ['range' => 'custom', 'from' => '2020-01-01', 'to' => '2020-01-31']))->assertInertia(fn (Assert $a) => $a->where('report.kpis.invoices', 0));
    }

    public function test_reports_stay_inside_the_current_business_even_for_a_super_admin(): void
    {
        $this->invoiceFrom($this->saveInvoice([$this->line($this->product, $this->unit['Tablet'], 2, 10)]));

        $other = \App\Models\Business::create(['name' => 'Other']);
        $otherOwner = User::create(['name' => 'O', 'email' => 'o@example.test', 'password' => bcrypt('x')]);
        $otherOwner->businesses()->attach($other->id, ['role' => 'owner']);
        $this->enterBusiness($other, $otherOwner);
        $this->supplier = \App\Models\Supplier::create(['name' => 'Other supplier']);
        [$p, $u] = $this->makeProduct('Theirs', ['Bottle' => 1], ['Bottle' => [500, 300]]);
        $this->purchase($p, $u['Bottle'], 3, 300, 500, now()->addYear()->toDateString());
        $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Bottle'], 1, 500)]));

        $admin = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => bcrypt('x')]);
        $admin->assignRole(Role::findOrCreate('super-admin'));
        $this->actingAs($admin)->withSession(['business_id' => $this->business->id])
            ->get(route('reports.sales', ['range' => 'today']))->assertInertia(fn (Assert $a) => $a->where('report.kpis.net_sales', 20)->where('report.kpis.invoices', 1));
    }
}
