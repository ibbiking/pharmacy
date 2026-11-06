<?php

namespace Tests\Feature\Catalogue;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Preference;
use App\Models\Product;
use App\Services\Sales\SalePreferenceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Step 5.4d: business-wide sale preferences and per-product overrides, read back by the sale engine. */
class SalePreferencesTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
    }

    private function settings(Product $product)
    {
        app()->forgetInstance(SalePreferenceResolver::class);

        return app(SalePreferenceResolver::class)->forProduct($product->fresh());
    }

    public function test_business_screen_shows_defaults_and_saves_every_rule_for_the_engine(): void
    {
        [$product] = $this->makeProduct('Alpha', ['Box' => 1, 'Tablet' => 10]);

        $this->get(route('global-sale-price-preferences.index'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Settings/SalePreferences')
            ->where('settings.price_mode', 'static-price')->where('settings.lot_mode', 'fefo')
            ->where('settings.allow_price_change', true)->where('settings.max_line_discount_percent', null));

        $this->post(route('global-sale-price-preferences.store'), [
            'price_mode' => 'stock-wise-price', 'include_tax' => true, 'lot_mode' => 'fifo', 'allow_price_change' => true,
            'min_price_percent' => 90, 'max_line_discount_percent' => 15, 'max_invoice_discount_percent' => null,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $s = $this->settings($product);
        $this->assertSame('stock-wise-price', $s->priceMode);
        $this->assertTrue($s->includeTax);
        $this->assertSame('fifo', $s->lotMode);
        $this->assertSame(90.0, $s->minPricePercent);
        $this->assertSame(15.0, $s->maxLineDiscountPercent);
        $this->assertNull($s->maxInvoiceDiscountPercent);
        $this->assertSame(1, Preference::where('type', 'sale_price')->whereIn('slug', ['static-price', 'stock-wise-price', 'previous-inventory-price'])->where('status', true)->count(), 'exactly one price mode is on');
        $this->assertTrue(AuditLog::query()->where('subject_type', Preference::class)->exists(), 'changes are audited');

        // Switching a limit off clears it.
        $this->post(route('global-sale-price-preferences.store'), ['price_mode' => 'static-price', 'lot_mode' => 'fefo', 'allow_price_change' => false, 'max_line_discount_percent' => null]);
        $s = $this->settings($product);
        $this->assertFalse($s->allowPriceChange);
        $this->assertNull($s->maxLineDiscountPercent);
    }

    public function test_business_screen_validation(): void
    {
        $this->post(route('global-sale-price-preferences.store'), ['price_mode' => 'cheapest', 'lot_mode' => 'random'])->assertSessionHasErrors(['price_mode', 'lot_mode']);
        $this->post(route('global-sale-price-preferences.store'), ['price_mode' => 'static-price', 'lot_mode' => 'fefo', 'max_invoice_discount_percent' => 150])
            ->assertSessionHasErrors('max_invoice_discount_percent');
    }

    public function test_product_overrides_win_and_can_go_back_to_the_business_setting(): void
    {
        [$product, $u] = $this->makeProduct('Beta', ['Box' => 1, 'Tablet' => 10]);

        $this->get(route('products.sale-price-preferences', $product->id))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Products/SalePreferences')->where('overrides.price_mode', null)->where('overrides.lot_mode', null)->has('units', 2)
            ->where('defaults.lot_mode', 'fefo')->where('business.id', $this->business->id)); // the shared business prop must survive

        $this->post(route('products.sale-price-preferences.store', $product->id), [
            'price_mode' => 'previous-inventory-price', 'include_tax' => true, 'lot_mode' => 'fifo', 'allow_price_change' => false,
            'min_price_percent' => 80, 'min_indicated_qty' => 3, 'min_qty_category_id' => $u['Box'],
        ])->assertRedirect(route('products.show', $product->id));

        $s = $this->settings($product);
        $this->assertSame(['previous-inventory-price', true, 'fifo', false, 80.0, 'product'], [$s->priceMode, $s->includeTax, $s->lotMode, $s->allowPriceChange, $s->minPricePercent, $s->priceModeSource]);
        $this->assertSame(3.0, (float) $product->fresh()->min_indicated_qty);
        $this->assertTrue(AuditLog::query()->where('action', 'sale_preferences_updated')->where('subject_id', $product->id)->exists());

        // Back to "business setting" for everything.
        $this->post(route('products.sale-price-preferences.store', $product->id), ['price_mode' => null, 'lot_mode' => null, 'allow_price_change' => null]);
        $p = $product->fresh();
        $this->assertNull($p->sale_price_preference_id);
        $this->assertFalse((bool) $p->sale_price_including_tax);
        $this->assertNull($p->lot_selection_mode);
        $this->assertNull($p->allow_price_change);
        $this->assertNull($p->min_indicated_qty);
        $this->assertSame('business', $this->settings($product)->priceModeSource);
    }

    public function test_product_rules_and_tenancy(): void
    {
        [$product] = $this->makeProduct('Gamma', ['Box' => 1, 'Tablet' => 10]);
        [, $other] = $this->makeProduct('Delta', ['Carton' => 1, 'Sachet' => 5]);

        $this->post(route('products.sale-price-preferences.store', $product->id), ['min_indicated_qty' => 5])->assertSessionHasErrors('min_qty_category_id');
        $this->post(route('products.sale-price-preferences.store', $product->id), ['min_indicated_qty' => 5, 'min_qty_category_id' => $other['Carton']])
            ->assertSessionHasErrors('min_qty_category_id'); // a unit that isn't in this product's packaging
        $this->post(route('products.sale-price-preferences.store', $product->id), ['min_price_percent' => 101, 'lot_mode' => 'lifo'])
            ->assertSessionHasErrors(['min_price_percent', 'lot_mode']);

        $foreign = Product::withoutGlobalScopes()->forceCreate(['product_name' => 'Foreign', 'business_id' => Business::create(['name' => 'Other'])->id, 'company_id' => $product->company_id, 'product_type_id' => $product->product_type_id]);
        $this->get(route('products.sale-price-preferences', $foreign->id))->assertNotFound();
        $this->post(route('products.sale-price-preferences.store', $foreign->id), ['lot_mode' => 'fifo'])->assertNotFound();
    }
}
