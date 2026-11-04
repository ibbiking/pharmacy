<?php

namespace Tests\Support;

use App\Models\BaseStockSalePrice;
use App\Models\Business;
use App\Models\Category;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Preference;
use App\Models\Product;
use App\Models\ProductParameter;
use App\Models\ProductPreference;
use App\Models\ProductType;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

/**
 * Builds realistic sale scenarios through the real HTTP routes (purchases, POS check-stock,
 * POS save, returns) for one business with one owner.
 */
trait SalesScenario
{
    protected Business $business;
    protected User $owner;
    protected ?Supplier $supplier = null;

    protected function setUpBusiness(string $name = 'Test Pharmacy'): void
    {
        Role::findOrCreate('Business Owner');
        Role::findOrCreate('sales-person');

        $this->owner = User::create([
            'name' => 'Owner',
            'email' => 'owner'.uniqid().'@example.test',
            'password' => bcrypt('secret-password'),
        ]);
        $this->owner->assignRole('Business Owner');

        $this->business = Business::create(['name' => $name]);
        $this->owner->businesses()->attach($this->business->id, ['role' => 'owner']);

        $this->enterBusiness($this->business, $this->owner);
        $this->supplier = Supplier::create(['name' => 'Main Supplier']);
    }

    protected function enterBusiness(Business $business, User $user): void
    {
        session(['business_id' => $business->id]);
        $this->actingAs($user)->withSession(['business_id' => $business->id]);
    }

    /**
     * Product with packaging chain, e.g. ['Box' => 1, 'Strip' => 1, 'Tablet' => 14]:
     * first entry is the top unit (qty ignored), each next entry = how many fit in the previous one.
     * $static: unit name => [sale, purchase] static prices.
     *
     * @return array{0: Product, 1: array<string, int>} product + unit name => category id
     */
    protected function makeProduct(string $name, array $chain = ['Box' => 1, 'Strip' => 1, 'Tablet' => 14], array $static = []): array
    {
        $company = Company::firstOrCreate(['name' => 'ACME Pharma']);
        $type = ProductType::firstOrCreate(['name' => 'Tablet']);

        $product = Product::create([
            'product_name' => $name,
            'company_id' => $company->id,
            'product_type_id' => $type->id,
            'is_draft' => false,
        ]);

        $ids = [];
        foreach (array_keys($chain) as $unit) {
            $ids[$unit] = Category::firstOrCreate(['name' => $unit])->id;
        }

        $units = array_keys($chain);
        $root = $ids[$units[0]];
        $this->param($product, $root, $root, $root, 1, $static[$units[0]] ?? [0, 0]);
        for ($i = 1; $i < count($units); $i++) {
            $this->param($product, $root, $ids[$units[$i - 1]], $ids[$units[$i]], $chain[$units[$i]], $static[$units[$i]] ?? [0, 0]);
        }

        return [$product->fresh(), $ids];
    }

    private function param(Product $product, int $root, int $parent, int $child, float $qty, array $static): void
    {
        ProductParameter::create([
            'product_id' => $product->id,
            'category_id' => $root,
            'parent_category_id' => $parent,
            'child_category_id' => $child,
            'quantity' => $qty,
            'static_category_unit_sale_price' => $static[0],
            'static_category_unit_purchase_price' => $static[1],
        ]);
    }

    /**
     * Record a purchase through the real PurchaseController::store route.
     * $saleTaxes: list of [Tax, rate%] applied on the sale price.
     */
    protected function purchase(Product $product, int $categoryId, float $qty, float $unitCost, float $unitSale, string $expiry, array $saleTaxes = [], float $extraPaidPercent = 0, ?string $batch = null): BaseStockSalePrice
    {
        $saleTaxRows = [];
        $saleTaxTotal = 0;
        foreach ($saleTaxes as [$tax, $rate]) {
            $unit = round($unitSale * $rate / 100, 6);
            $saleTaxRows[] = ['id' => $tax->id, 'rate' => $rate, 'unit' => $unit, 'total' => round($unit * $qty, 6)];
            $saleTaxTotal += $unit * $qty;
        }

        $extraPerUnit = round($unitCost * $extraPaidPercent / 100, 6);

        $this->postJson(route('purchases.store'), [
            'product' => $product->id,
            'category' => $categoryId,
            'supplier' => $this->supplier->id,
            'quantity' => $qty,
            'unit_cost_price' => $unitCost,
            'paid_unit_cost_price' => $unitCost + $extraPerUnit,
            'extra_paid_per_unit' => $extraPerUnit,
            'extra_paid_percent' => $extraPaidPercent,
            'unit_sale_price' => $unitSale,
            'unit_cost_tax_amount' => 0,
            'total_cost_tax_amount' => 0,
            'unit_sale_tax_amount' => $qty > 0 ? $saleTaxTotal / $qty : 0,
            'total_sale_tax_amount' => $saleTaxTotal,
            'sale_taxes' => $saleTaxRows,
            'expiry_date' => $expiry,
            'batch_no' => $batch,
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        return BaseStockSalePrice::query()->where('product_id', $product->id)->latest('id')->firstOrFail();
    }

    protected function tax(string $name, float $rate): Tax
    {
        return Tax::create(['name' => $name, 'rate' => $rate]);
    }

    /** Business-wide preferences. */
    protected function setBusinessPricing(string $priceMode, bool $includeTax = false): void
    {
        Preference::where('type', 'sale_price')->whereIn('slug', ['static-price', 'stock-wise-price', 'previous-inventory-price'])->update(['status' => false]);
        Preference::where('type', 'sale_price')->where('slug', $priceMode)->update(['status' => true]);
        Preference::where('type', 'sale_price')->where('slug', 'sale-price-including-tax')->update(['status' => $includeTax]);
        $this->forgetResolvedSettings();
    }

    protected function setBusinessLotMode(string $mode): void
    {
        Preference::where('type', 'lot_selection')->update(['status' => false]);
        Preference::where('type', 'lot_selection')->where('slug', $mode)->update(['status' => true]);
        $this->forgetResolvedSettings();
    }

    protected function setPosRule(string $slug, bool $on, $value = null): void
    {
        Preference::where('type', 'pos')->where('slug', $slug)->update(['status' => $on, 'value' => $value]);
        $this->forgetResolvedSettings();
    }

    protected function setProductPricing(Product $product, string $priceMode, bool $includeTax = false): void
    {
        $pp = ProductPreference::firstOrCreate(['type' => 'sale_price', 'slug' => $priceMode], ['preference' => $priceMode]);
        $product->update(['sale_price_preference_id' => $pp->id, 'sale_price_including_tax' => $includeTax]);
        $this->forgetResolvedSettings();
    }

    protected function forgetResolvedSettings(): void
    {
        app()->forgetInstance(\App\Services\Sales\SalePreferenceResolver::class);
        app()->forgetInstance(\App\Services\Sales\PackagingService::class);
    }

    /** POS "check stock" for a quantity, as the POS screen calls it. */
    protected function checkStock(Product $product, float $qty, int $categoryId, array $currentCart = [], array $extra = []): TestResponse
    {
        return $this->postJson(route('products.pos.checkStock'), array_merge([
            'product_id' => $product->id,
            'quantity' => $qty,
            'category_id' => $categoryId,
            'current_cart' => json_encode($currentCart),
        ], $extra));
    }

    /** Cart line exactly as the POS JavaScript posts it to save-invoice. */
    protected function line(Product $product, int $categoryId, float $qty, float $price, array $overrides = []): array
    {
        return array_merge([
            'id' => $product->id,
            'product_id' => $product->id,
            'category_id' => $categoryId,
            'name' => $product->product_name,
            'price' => $price,
            'qty' => $qty,
            'base_qty' => 0, // deliberately wrong: the server must not trust it
            'price_group' => number_format($price, 2, '.', ''),
            'base_stock_sale_price_id' => null,
            'discount_selected_type' => 'percent',
            'discount_percent' => 0,
            'discount_amount' => 0,
            'max_discount_percent' => 0,
            'max_discount_amount' => 0,
        ], $overrides);
    }

    /** Turn check-stock response rows into cart lines (what the POS screen does). */
    protected function linesFromRows(Product $product, array $rows): array
    {
        return array_map(fn ($r) => $this->line($product, (int) $r['category_id'], (float) $r['quantity'], (float) $r['unit_price'], [
            'base_stock_sale_price_id' => $r['base_stock_sale_price_id'] ?? null,
            'price_group' => $r['price_group'] ?? number_format((float) $r['unit_price'], 2, '.', ''),
        ]), $rows);
    }

    protected function saveInvoice(array $lines, array $invoice = []): TestResponse
    {
        return $this->postJson(route('pos.save-invoice'), array_merge([
            'cart' => $lines,
            'invoice_discount_type' => 'amount',
            'invoice_discount_value' => 0,
            'invoice_discount_amount' => 0,
            'cash_received' => 100000,
            // client totals are ignored by the server; send nonsense to prove it
            'subtotal' => 1, 'total' => 1, 'grand_total' => 1, 'change_return' => 1,
        ], $invoice));
    }

    protected function invoiceFrom(TestResponse $response): Invoice
    {
        $response->assertOk()->assertJson(['success' => true]);

        return Invoice::with('items.taxes', 'items.lots')->findOrFail($response->json('invoice_id'));
    }

    protected function remaining(BaseStockSalePrice $lot): float
    {
        return (float) $lot->fresh()->remaining_base_stock;
    }
}
