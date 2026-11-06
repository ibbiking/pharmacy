<?php

namespace Tests\Feature\Stock;

use App\Models\BaseStockSalePrice;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\SaleTax;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.5a: purchases are computed and checked on the server (P-1..P-5), edit and archive keep stock honest. */
class PurchaseServiceTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    private function bill(array $line, array $head = []): array
    {
        return $head + ['supplier' => ['id' => $this->supplier->id], 'invoice_no' => 'B-77', 'lines' => [$line]];
    }

    private function purchaseLine(Product $p, int $unit, array $extra = []): array
    {
        return $extra + ['product_id' => $p->id, 'category_id' => $unit, 'quantity' => 2, 'unit_cost_price' => 100, 'unit_sale_price' => 150,
            'expiry_date' => now()->addYear()->toDateString(), 'batch_no' => 'L1'];
    }

    public function test_amounts_are_computed_by_the_server_and_written_for_the_engine(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Strip' => 2, 'Tablet' => 10]); // 1 box = 20 tablets
        $gst = Tax::create(['name' => 'Input tax', 'rate' => 5]);
        $st = Tax::create(['name' => 'Sales Tax', 'rate' => 17]);

        $this->post(route('purchases.store'), $this->bill($this->purchaseLine($p, $u['Box'], [
            'paid_unit_cost_price' => 110, 'purchase_tax_ids' => [$gst->id], 'sale_tax_ids' => [$st->id],
            // forged client amounts are ignored (P-1)
            'unit_sale_tax_amount' => 9999, 'total_cost_price' => 1,
        ])))->assertSessionHasNoErrors()->assertRedirect(route('purchases.index'));

        $purchase = Purchase::firstOrFail();
        $this->assertEqualsWithDelta(40, $purchase->base_quantity, 1e-9);
        $this->assertEqualsWithDelta(200, $purchase->total_cost_price, 1e-9);
        $this->assertEqualsWithDelta(220, $purchase->paid_extra_total_cost_price, 1e-9);
        $this->assertEqualsWithDelta(10, $purchase->extra_paid_percent, 1e-9);          // GST disclosure: (110 − 100) ÷ 100
        $this->assertEqualsWithDelta(10, $purchase->total_cost_tax_amount, 1e-9);       // 5% of 100 × 2
        $this->assertEqualsWithDelta(25.5, $purchase->unit_sale_tax_amount, 1e-9);      // 17% of 150
        $this->assertEqualsWithDelta(0.25, $purchase->base_unit_purchase_tax_price, 1e-9);
        $this->assertSame(17.0, (float) SaleTax::where('purchase_id', $purchase->id)->value('tax_rate'));

        $lot = BaseStockSalePrice::where('purchase_id', $purchase->id)->firstOrFail();
        $this->assertEqualsWithDelta(40, $lot->remaining_base_stock, 1e-9);
        $this->assertEqualsWithDelta(7.5, $lot->base_category_unit_sale_price, 1e-9);   // 300 ÷ 40
        $this->assertEqualsWithDelta(1.275, $lot->base_category_unit_sale_tax_price, 1e-9);
        $this->assertEqualsWithDelta(40, ProductStock::where('product_id', $p->id)->value('current_stock'), 1e-9);

        // The POS sells it at the batch price, in any unit.
        $this->checkStock($p, 1, $u['Strip'])->assertOk();
    }

    public function test_one_bill_with_several_lines_and_a_typed_new_supplier(): void
    {
        [$a, $ua] = $this->makeProduct('A', ['Box' => 1, 'Tablet' => 10]);
        [$b, $ub] = $this->makeProduct('B', ['Bottle' => 1]);

        $this->post(route('purchases.store'), ['supplier' => ['name' => 'New Traders'], 'invoice_no' => 'INV-9', 'lines' => [
            $this->purchaseLine($a, $ua['Box']), $this->purchaseLine($b, $ub['Bottle'], ['quantity' => 12]),
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2, Purchase::where('invoice_no', 'INV-9')->count());
        $this->assertSame(1, Purchase::query()->distinct()->count('supplier_id'));
        $this->assertSame($this->business->id, \App\Models\Supplier::where('name', 'New Traders')->value('business_id'));
    }

    public function test_inputs_are_checked_against_this_business_and_the_rules(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);
        [, $other] = $this->makeProduct('Beta', ['Carton' => 1, 'Sachet' => 5]);
        $foreignBiz = Business::create(['name' => 'Other']);
        $foreign = Product::withoutGlobalScopes()->forceCreate(['product_name' => 'Foreign', 'business_id' => $foreignBiz->id, 'company_id' => $p->company_id, 'product_type_id' => $p->product_type_id, 'is_draft' => false]);
        $foreignTax = Tax::withoutGlobalScopes()->forceCreate(['name' => 'X', 'rate' => 50, 'business_id' => $foreignBiz->id]);
        $draft = Product::create(['product_name' => 'Draft', 'is_draft' => true, 'company_id' => $p->company_id, 'product_type_id' => $p->product_type_id]);

        $err = fn (array $line) => $this->post(route('purchases.store'), $this->bill($line));
        $err($this->purchaseLine($foreign, $u['Box']))->assertSessionHasErrors('lines.0.product_id');            // P-2
        $err($this->purchaseLine($draft, $u['Box']))->assertSessionHasErrors('lines.0.product_id');              // P-5 drafts
        $err($this->purchaseLine($p, $other['Carton']))->assertSessionHasErrors('lines.0.category_id');          // P-2 unit
        $err($this->purchaseLine($p, $u['Box'], ['sale_tax_ids' => [$foreignTax->id]]))->assertSessionHasErrors('lines.0.sale_tax_ids');
        $err($this->purchaseLine($p, $u['Box'], ['quantity' => 0]))->assertSessionHasErrors('lines.0.quantity');
        $err($this->purchaseLine($p, $u['Box'], ['unit_cost_price' => '0.5', 'unit_sale_price' => 0.4]))->assertSessionHasErrors('lines.0.unit_sale_price'); // P-5: numbers, not text length
        $err($this->purchaseLine($p, $u['Box'], ['paid_unit_cost_price' => 90]))->assertSessionHasErrors('lines.0.paid_unit_cost_price');
        $err($this->purchaseLine($p, $u['Box'], ['manufacturing_date' => '2030-01-01', 'expiry_date' => '2029-01-01']))->assertSessionHasErrors('lines.0.expiry_date');
        $this->post(route('purchases.store'), ['lines' => [$this->purchaseLine($p, $u['Box'])]])->assertSessionHasErrors('supplier');

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, Purchase::withoutGlobalScopes()->where('product_id', $foreign->id)->count());
    }

    public function test_editing_after_sales_keeps_what_was_sold(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);
        $lot = $this->purchase($p, $u['Box'], 2, 100, 150, now()->addYear()->toDateString()); // 20 tablets
        $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 6, 15)]));
        $purchase = Purchase::firstOrFail();
        $put = fn (array $line) => $this->put(route('purchases.update', $purchase->id), ['supplier' => ['id' => $this->supplier->id], 'line' => $line]);
        $base = ['product_id' => $p->id, 'category_id' => $u['Box'], 'unit_cost_price' => 100, 'unit_sale_price' => 150, 'expiry_date' => now()->addYear()->toDateString()];

        $put($base + ['quantity' => 0.5])->assertSessionHasErrors('line.quantity');                     // 6 sold, 5 < 6
        $put(['quantity' => 3, 'unit_sale_price' => 1] + $base)->assertSessionHasErrors('line.unit_sale_price'); // field errors keep the form's keys
        $put(['category_id' => $u['Tablet'], 'quantity' => 30] + $base)->assertSessionHasErrors('line.category_id');
        $this->assertEqualsWithDelta(14, $this->remaining($lot), 1e-9);

        // P-3: raising the quantity keeps the 6 sold → remaining 30 − 6 = 24 (the old code reset it to 30).
        $put(['quantity' => 3, 'unit_sale_price' => 160] + $base)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(24, $this->remaining($lot), 1e-9);
        $this->assertEqualsWithDelta(30, $lot->fresh()->base_stock, 1e-9);
        $this->assertEqualsWithDelta(16, $lot->fresh()->base_category_unit_sale_price, 1e-9);
        $this->assertEqualsWithDelta(24, ProductStock::where('product_id', $p->id)->value('current_stock'), 1e-9);
    }

    public function test_archive_only_when_nothing_was_sold(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);
        $sold = $this->purchase($p, $u['Box'], 1, 100, 150, now()->addYear()->toDateString(), batch: 'SOLD');
        $this->invoiceFrom($this->saveInvoice([$this->line($p, $u['Tablet'], 2, 15, ['lot_selection_mode' => 'fifo'])]));
        $fresh = $this->purchase($p, $u['Box'], 1, 100, 150, now()->addYears(2)->toDateString(), batch: 'NEW');

        // P-4: a batch that was sold from can't be archived.
        $this->delete(route('purchases.destroy', $sold->purchase_id))->assertSessionHas('error');
        $this->assertNotSoftDeleted(Purchase::find($sold->purchase_id));

        $this->assertEqualsWithDelta(18, ProductStock::where('product_id', $p->id)->value('current_stock'), 1e-9);
        $this->delete(route('purchases.destroy', $fresh->purchase_id))->assertRedirect(route('purchases.index'));
        $this->assertSoftDeleted(Purchase::withTrashed()->find($fresh->purchase_id));
        $this->assertNull(BaseStockSalePrice::find($fresh->id), 'its batch leaves the shelf');
        $this->assertEqualsWithDelta(8, ProductStock::where('product_id', $p->id)->value('current_stock'), 1e-9);
        $this->checkStock($p, 9, $u['Tablet'])->assertJsonPath('status', 'partial'); // only 8 left
    }

    public function test_legacy_forms_still_work_but_their_amounts_are_ignored(): void
    {
        [$p, $u] = $this->makeProduct('Alpha', ['Bottle' => 1]);
        $st = Tax::create(['name' => 'Sales Tax', 'rate' => 10]);

        $this->postJson(route('purchases.store-multiple'), ['supplier' => $this->supplier->id, 'purchases' => [[
            'product' => $p->id, 'category' => $u['Bottle'], 'quantity' => 4, 'unit_cost_price' => 50, 'unit_sale_price' => 80,
            'expiry_date' => now()->addYear()->toDateString(), 'sale_taxes' => [['id' => $st->id, 'rate' => 99, 'unit' => 99, 'total' => 999]],
            'total_sale_tax_amount' => 999,
        ]]], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->assertJson(['success' => true]);

        $purchase = Purchase::firstOrFail();
        $this->assertEqualsWithDelta(32, $purchase->total_sale_tax_amount, 1e-9); // 10% of 80 × 4, not 999
        $this->assertSame(10.0, (float) SaleTax::where('purchase_id', $purchase->id)->value('tax_rate'));
    }
}
