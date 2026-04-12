<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPricesToGenericProductParameters extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('generic_product_parameters', function (Blueprint $table) {
            $table->decimal('static_category_unit_purchase_price', 15, 2)->nullable()->after('quantity');
            $table->decimal('static_category_unit_sale_price', 15, 2)->nullable()->after('static_category_unit_purchase_price');
        });

        // Backfill prices from original product_parameters table mapping directly by generic ID mapping since we seeded it 1:1
        \Illuminate\Support\Facades\DB::statement('
            UPDATE generic_product_parameters gpp
            JOIN product_parameters pp ON gpp.id = pp.id
            SET gpp.static_category_unit_purchase_price = pp.static_category_unit_purchase_price,
                gpp.static_category_unit_sale_price = pp.static_category_unit_sale_price
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('generic_product_parameters', function (Blueprint $table) {
            $table->dropColumn(['static_category_unit_purchase_price', 'static_category_unit_sale_price']);
        });
    }
}
