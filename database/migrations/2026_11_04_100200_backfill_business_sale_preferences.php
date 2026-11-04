<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration (Roadmap step 2, bug P2): every business gets its own business-wide sale
 * preference rows. Before this, only business #1 had rows (all switched off), so every other
 * business could not choose a business-wide preference and silently fell back to static price.
 *
 * Effective behaviour is preserved: "static" becomes the active sale-price preference wherever
 * none is active (that is what the code fell back to), and price changes at the POS stay allowed
 * (the POS price field was always editable). New per decision D1: lot selection defaults to
 * FEFO (earliest expiry first).
 *
 * Definitions are inlined on purpose (a migration must not depend on app code that may change).
 * New businesses get the same rows from App\Services\Business\BusinessPreferenceDefaults.
 */
return new class extends Migration
{
    private const ROWS = [
        // type, slug, label, default status, default value
        ['sale_price', 'static-price', 'Static Sale Price', true, null],
        ['sale_price', 'stock-wise-price', 'Stock wise Sale Price', false, null],
        ['sale_price', 'previous-inventory-price', 'Last/previous inventory Sale Price', false, null],
        ['sale_price', 'sale-price-including-tax', 'Sale Price Including tax', false, null],
        ['lot_selection', 'fefo', 'Earliest expiry first (FEFO)', true, null],
        ['lot_selection', 'fifo', 'Oldest purchase first (FIFO)', false, null],
        ['pos', 'allow-price-change', 'Allow cashier to change the sale price', true, null],
        ['pos', 'min-price-percent', 'Lowest allowed price (% of system price)', false, null],
        ['pos', 'max-line-discount-percent', 'Maximum discount per line (%)', false, null],
        ['pos', 'max-invoice-discount-percent', 'Maximum discount per invoice (%)', false, null],
    ];

    private const EXCLUSIVE_TYPES = [
        'sale_price' => ['static-price', 'stock-wise-price', 'previous-inventory-price'],
        'lot_selection' => ['fefo', 'fifo'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            foreach (self::ROWS as [$type, $slug, $label, $status, $value]) {
                $exists = DB::table('preferences')
                    ->where('business_id', $businessId)->where('type', $type)->where('slug', $slug)
                    ->whereNull('deleted_at')->exists();

                if (! $exists) {
                    DB::table('preferences')->insert([
                        'business_id' => $businessId, 'type' => $type, 'slug' => $slug,
                        'preference' => $label, 'status' => $status, 'value' => $value,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            // Exactly one active choice per exclusive group; keep an existing active choice.
            foreach (self::EXCLUSIVE_TYPES as $type => $slugs) {
                $active = DB::table('preferences')
                    ->where('business_id', $businessId)->where('type', $type)
                    ->whereIn('slug', $slugs)->where('status', true)->whereNull('deleted_at')
                    ->orderBy('id')->pluck('id');

                if ($active->isEmpty()) {
                    DB::table('preferences')
                        ->where('business_id', $businessId)->where('type', $type)
                        ->where('slug', $slugs[0])->whereNull('deleted_at')
                        ->update(['status' => true, 'updated_at' => $now]);
                } elseif ($active->count() > 1) {
                    DB::table('preferences')->whereIn('id', $active->slice(1)->all())
                        ->update(['status' => false, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // Rows created here are indistinguishable from user-edited ones; intentionally not removed.
    }
};
