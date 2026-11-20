<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * B2B Sales and B2B Purchasing are now part of the Wholesale & Distribution type (config/modules.php → types →
 * inventory_b2b → locked; owner's decision 2026-11-20). A wholesale business that had switched either one off gets
 * it back on (its data was kept). Pharmacy and Retail businesses are not touched: they can never have them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (DB::table('businesses')->where('business_type', 'inventory_b2b')->pluck('id') as $businessId) {
            foreach (['b2b_sales', 'b2b_purchase', 'customer_credit', 'supplier_credit'] as $module) {
                $row = DB::table('business_modules')->where('business_id', $businessId)->where('module', $module)->first();
                if (! $row) {
                    DB::table('business_modules')->insert(['business_id' => $businessId, 'module' => $module, 'status' => 'active', 'source' => 'free',
                        'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                } elseif ($row->status !== 'active' || $row->deleted_at !== null) {
                    DB::table('business_modules')->where('id', $row->id)->update(['status' => 'active', 'deleted_at' => null,
                        'activated_at' => $now, 'expires_at' => null, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // Kept on: switching them off would hide documents the business already has.
    }
};
