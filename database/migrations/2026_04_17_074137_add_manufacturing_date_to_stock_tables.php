<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManufacturingDateToStockTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('manufacturing_date')->nullable()->after('expiry_date');
        });

        Schema::table('stock_prices', function (Blueprint $table) {
            $table->date('manufacturing_date')->nullable()->after('expiry_date');
        });

        Schema::table('base_stock_sale_price', function (Blueprint $table) {
            $table->date('manufacturing_date')->nullable()->after('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('manufacturing_date');
        });

        Schema::table('stock_prices', function (Blueprint $table) {
            $table->dropColumn('manufacturing_date');
        });

        Schema::table('base_stock_sale_price', function (Blueprint $table) {
            $table->dropColumn('manufacturing_date');
        });
    }
}
