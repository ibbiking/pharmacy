<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Can this item expire?" per product. When on, every purchase / goods receipt of it needs an expiry date;
 * when off the expiry is optional and the POS sells oldest stock first.
 * Backfill: on for businesses with the Pharmacy Pack (or a pharmacy business type) and for any product
 * that already has a batch with an expiry date; off for everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('has_expiry')->default(false)->after('lot_selection_mode');
        });

        $pharmacyBusinesses = DB::table('business_modules')->where('module', 'pharmacy')->pluck('business_id')
            ->merge(DB::table('businesses')->where('business_type', 'pharmacy')->pluck('id'))->unique()->values();

        DB::table('products')->whereIn('business_id', $pharmacyBusinesses)->update(['has_expiry' => true]);
        DB::table('products')->whereIn('id', DB::table('base_stock_sale_price')->whereNotNull('expiry_date')->select('product_id'))
            ->update(['has_expiry' => true]);
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('has_expiry'));
    }
};
