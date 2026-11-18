<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New micro services "Customer credit" and "Supplier credit" (docs/BUSINESS_TYPES_AND_SERVICES.md): on by default in
 * every business type. Payments, statements and ageing moved to them from B2B Sales / B2B Purchasing, so:
 *  - every existing business gets both switched on (like a new business does);
 *  - a custom role that could see / record B2B payments keeps that ability through the new permissions.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'b2b_sales.view' => ['customer_credit.view'],
        'b2b_sales.payments' => ['customer_credit.view', 'customer_credit.payments'],
        'b2b_purchase.view' => ['supplier_credit.view'],
        'b2b_purchase.payments' => ['supplier_credit.view', 'supplier_credit.payments'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            foreach (['customer_credit', 'supplier_credit'] as $module) {
                $row = DB::table('business_modules')->where('business_id', $businessId)->where('module', $module)->first();
                if (! $row) {
                    DB::table('business_modules')->insert(['business_id' => $businessId, 'module' => $module, 'status' => 'active', 'source' => 'free',
                        'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        foreach (self::PERMISSIONS as $old => $new) {
            foreach (DB::table('business_role_permissions')->where('permission', $old)->pluck('business_role_id')->unique() as $roleId) {
                foreach ($new as $permission) {
                    $exists = DB::table('business_role_permissions')->where('business_role_id', $roleId)->where('permission', $permission)->exists();
                    if (! $exists) {
                        DB::table('business_role_permissions')->insert(['business_role_id' => $roleId, 'permission' => $permission]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Kept: removing the modules would hide payments that businesses already recorded.
    }
};
