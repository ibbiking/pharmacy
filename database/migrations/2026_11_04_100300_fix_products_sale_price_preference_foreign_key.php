<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * products.sale_price_preference_id holds a product_preferences id (the global list of product-level
 * options; that is what the product screen saves and what the pricing code reads), but its foreign
 * key pointed at `preferences` (the per-business rows) with ON DELETE CASCADE, so deleting a
 * business preference row would have deleted products. It only worked because ids happened to match.
 *
 * Now: FK → product_preferences(id), ON DELETE SET NULL (product falls back to the business-wide
 * preference). Values with no matching product_preferences row are cleared first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['sale_price_preference_id']);
        });

        DB::table('products')
            ->whereNotNull('sale_price_preference_id')
            ->whereNotIn('sale_price_preference_id', DB::table('product_preferences')->select('id'))
            ->update(['sale_price_preference_id' => null]);

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('sale_price_preference_id')->references('id')->on('product_preferences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['sale_price_preference_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('sale_price_preference_id')->references('id')->on('preferences')->onDelete('cascade');
        });
    }
};
