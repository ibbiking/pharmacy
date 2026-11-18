<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One business = one business type (owner's rule 2026-11-19, docs/BUSINESS_TYPES_AND_SERVICES.md).
 * Existing businesses that switched on modules of another type get them switched off (the row is archived;
 * the data behind it is kept and nothing is deleted), and the type's own locked modules are switched on.
 *
 * The lists are a snapshot of config/modules.php → types on 2026-11-19, so later config changes never
 * change what this migration did.
 */
return new class extends Migration
{
    private const ALLOWED = [
        'pharmacy' => ['core', 'stock', 'pos', 'pharmacy', 'inventory_pro', 'accounting', 'expense', 'analytics', 'fbr'],
        'inventory' => ['core', 'stock', 'pos', 'inventory_pro', 'accounting', 'expense', 'analytics', 'fbr'],
        'inventory_b2b' => ['core', 'stock', 'pos', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense', 'analytics', 'fbr'],
    ];

    private const LOCKED = [
        'pharmacy' => ['stock', 'pos', 'pharmacy'],
        'inventory' => ['stock', 'pos'],
        'inventory_b2b' => ['stock', 'pos'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (DB::table('businesses')->select('id', 'business_type')->get() as $business) {
            $type = isset(self::ALLOWED[$business->business_type]) ? $business->business_type : 'pharmacy';

            DB::table('business_modules')->where('business_id', $business->id)->whereNull('deleted_at')
                ->whereNotIn('module', self::ALLOWED[$type])
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            foreach (self::LOCKED[$type] as $module) {
                $row = DB::table('business_modules')->where('business_id', $business->id)->where('module', $module)->first();
                if (! $row) {
                    DB::table('business_modules')->insert([
                        'business_id' => $business->id, 'module' => $module, 'status' => 'active', 'source' => 'type',
                        'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                } elseif ($row->deleted_at !== null || $row->status !== 'active') {
                    DB::table('business_modules')->where('id', $row->id)
                        ->update(['deleted_at' => null, 'status' => 'active', 'expires_at' => null, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        // Not reversed: which modules a business had switched on before is not recorded, and switching modules of
        // another type back on would break the rule. The archived rows stay in the table.
    }
};
