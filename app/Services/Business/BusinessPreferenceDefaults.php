<?php

namespace App\Services\Business;

use Illuminate\Support\Facades\DB;

/**
 * Creates the business-wide preference rows every business must have (idempotent).
 * Runs automatically when a Business is created (see Business::booted()); the data migration
 * 2026_11_04_100200 applied the same defaults to businesses that already existed.
 */
class BusinessPreferenceDefaults
{
    /** type, slug, label, default status, default value */
    public const ROWS = [
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

    public function ensure(int $businessId): void
    {
        $now = now();

        foreach (self::ROWS as [$type, $slug, $label, $status, $value]) {
            // Query builder on purpose: bypasses the session-based BusinessScope/stamping so rows are
            // always written for $businessId, whatever business the current session is in.
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
    }
}
