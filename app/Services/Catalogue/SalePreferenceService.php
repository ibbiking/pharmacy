<?php

namespace App\Services\Catalogue;

use App\Models\Preference;
use App\Models\Product;
use App\Models\ProductPreference;
use App\Services\Business\BusinessPreferenceDefaults;
use App\Services\Sales\SalePreferenceResolver;
use App\Services\Sales\SaleSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sale preferences screens (Roadmap 5.4d): business-wide rows in `preferences` and per-product overrides on `products`
 * (NULL = use the business setting). SalePreferenceResolver stays the only reader at sale time; this class only
 * reads/writes the same storage with validation. Every change is audited (Auditable on Preference; an explicit
 * audit entry for product overrides).
 */
class SalePreferenceService
{
    private const NUMERIC = ['min-price-percent', 'max-line-discount-percent', 'max-invoice-discount-percent'];

    public function __construct(private readonly SalePreferenceResolver $resolver, private readonly ProductSetupService $setup)
    {
    }

    /** @return array{price_mode: string, include_tax: bool, lot_mode: string, allow_price_change: bool, min_price_percent: ?float, max_line_discount_percent: ?float, max_invoice_discount_percent: ?float} */
    public function business(int $businessId): array
    {
        app(BusinessPreferenceDefaults::class)->ensure($businessId);
        $rows = $this->rows($businessId);
        $on = fn (string $type, string $slug) => (bool) optional($rows->get("{$type}:{$slug}"))->status;
        $num = fn (string $slug) => ($r = $rows->get("pos:{$slug}")) && $r->status && is_numeric($r->value) ? (float) $r->value : null;

        return [
            'price_mode' => collect(SaleSettings::PRICE_MODES)->first(fn ($m) => $on('sale_price', $m)) ?? SaleSettings::PRICE_STATIC,
            'include_tax' => $on('sale_price', 'sale-price-including-tax'),
            'lot_mode' => $on('lot_selection', SaleSettings::LOT_FIFO) && ! $on('lot_selection', SaleSettings::LOT_FEFO) ? SaleSettings::LOT_FIFO : SaleSettings::LOT_FEFO,
            'allow_price_change' => $rows->has('pos:allow-price-change') ? $on('pos', 'allow-price-change') : true,
            'min_price_percent' => $num('min-price-percent'),
            'max_line_discount_percent' => $num('max-line-discount-percent'),
            'max_invoice_discount_percent' => $num('max-invoice-discount-percent'),
        ];
    }

    public function saveBusiness(int $businessId, array $input): void
    {
        $data = validator($input, [
            'price_mode' => ['required', Rule::in(SaleSettings::PRICE_MODES)],
            'include_tax' => ['boolean'],
            'lot_mode' => ['required', Rule::in(SaleSettings::LOT_MODES)],
            'allow_price_change' => ['boolean'],
            'min_price_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_line_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_invoice_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [], [
            'min_price_percent' => 'lowest price', 'max_line_discount_percent' => 'line discount limit', 'max_invoice_discount_percent' => 'invoice discount limit',
        ])->validate();

        app(BusinessPreferenceDefaults::class)->ensure($businessId);

        DB::transaction(function () use ($businessId, $data) {
            $rows = $this->rows($businessId);
            // Model saves (not mass updates) so each change goes through the Auditable trait.
            $set = function (string $key, bool $status, $value = null) use ($rows) {
                $row = $rows->get($key);
                if ($row && ((bool) $row->status !== $status || (string) $row->value !== (string) $value)) {
                    $row->status = $status;
                    $row->value = $value;
                    $row->save();
                }
            };

            foreach (SaleSettings::PRICE_MODES as $mode) {
                $set("sale_price:{$mode}", $data['price_mode'] === $mode);
            }
            $set('sale_price:sale-price-including-tax', (bool) ($data['include_tax'] ?? false));
            foreach (SaleSettings::LOT_MODES as $mode) {
                $set("lot_selection:{$mode}", $data['lot_mode'] === $mode);
            }
            $set('pos:allow-price-change', (bool) ($data['allow_price_change'] ?? false));
            foreach (self::NUMERIC as $slug) {
                $v = $data[str_replace('-', '_', $slug)] ?? null;
                $set("pos:{$slug}", $v !== null && $v !== '', $v === null || $v === '' ? null : (string) round((float) $v, 2));
            }
        });

        $this->resolver->forget();
    }

    /** Per-product overrides; null = use the business setting. */
    public function product(Product $product): array
    {
        $slug = $product->sale_price_preference_id ? ProductPreference::withoutGlobalScopes()->whereKey($product->sale_price_preference_id)->value('slug') : null;
        $priceMode = in_array($slug, SaleSettings::PRICE_MODES, true) ? $slug : null;

        return [
            'price_mode' => $priceMode,
            'include_tax' => $priceMode ? (bool) $product->sale_price_including_tax : null,
            'lot_mode' => in_array($product->lot_selection_mode, SaleSettings::LOT_MODES, true) ? $product->lot_selection_mode : null,
            'allow_price_change' => $product->allow_price_change === null ? null : (bool) $product->allow_price_change,
            'min_price_percent' => $product->min_price_percent === null ? null : (float) $product->min_price_percent,
            'min_indicated_qty' => $product->min_indicated_qty === null ? null : (float) $product->min_indicated_qty,
            'min_qty_category_id' => $product->min_qty_category_id ? (int) $product->min_qty_category_id : null,
        ];
    }

    public function saveProduct(Product $product, array $input): void
    {
        $units = collect($this->setup->chain($product))->pluck('category_id')->all();
        $data = validator($input, [
            'price_mode' => ['nullable', Rule::in(SaleSettings::PRICE_MODES)],
            'include_tax' => ['nullable', 'boolean'],
            'lot_mode' => ['nullable', Rule::in(SaleSettings::LOT_MODES)],
            'allow_price_change' => ['nullable', 'boolean'],
            'min_price_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_indicated_qty' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'min_qty_category_id' => ['nullable', 'required_with:min_indicated_qty', 'integer', Rule::in($units)],
        ], [
            'min_qty_category_id.in' => 'Pick one of this product’s packaging units.',
            'min_qty_category_id.required_with' => 'Pick the unit the alert quantity is counted in.',
        ], ['min_indicated_qty' => 'alert quantity', 'min_price_percent' => 'lowest price'])->validate();

        $mode = $data['price_mode'] ?? null;
        $before = $this->product($product);
        $product->update([
            // Shared reference rows (SalePricePreferenceSeeder); created here too so a missing seed can't silently drop the choice.
            'sale_price_preference_id' => $mode ? ProductPreference::firstOrCreate(['type' => 'sale_price', 'slug' => $mode], ['preference' => $mode])->id : null,
            // Tax inclusion travels with the product's price mode (SalePreferenceResolver reads them as a pair).
            'sale_price_including_tax' => $mode ? (bool) ($data['include_tax'] ?? false) : false,
            'lot_selection_mode' => $data['lot_mode'] ?? null,
            'allow_price_change' => array_key_exists('allow_price_change', $data) && $data['allow_price_change'] !== null ? (bool) $data['allow_price_change'] : null,
            'min_price_percent' => isset($data['min_price_percent']) && $data['min_price_percent'] !== '' ? round((float) $data['min_price_percent'], 2) : null,
            'min_indicated_qty' => isset($data['min_indicated_qty']) && $data['min_indicated_qty'] !== '' ? round((float) $data['min_indicated_qty'], 2) : null,
            'min_qty_category_id' => isset($data['min_indicated_qty']) && $data['min_indicated_qty'] !== '' ? (int) $data['min_qty_category_id'] : null,
        ]);

        $after = $this->product($product->fresh());
        $changes = collect($after)->filter(fn ($v, $k) => $before[$k] !== $v)->map(fn ($v, $k) => ['from' => $before[$k], 'to' => $v])->all();
        if ($changes) {
            app(\App\Services\Audit\AuditLogger::class)->record('sale_preferences_updated', $product, $changes, "Sale preferences of {$product->product_name} changed");
        }

        $this->resolver->forget();
    }

    private function rows(int $businessId)
    {
        return Preference::withoutGlobalScopes()->where('business_id', $businessId)->whereNull('deleted_at')->get()
            ->keyBy(fn ($r) => $r->type.':'.$r->slug);
    }
}
