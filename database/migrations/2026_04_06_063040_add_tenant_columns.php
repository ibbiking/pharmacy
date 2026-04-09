<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTenantColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'categories', 'companies', 'farmulas', 'products', 'product_types', 
            'strengths', 'suppliers', 'taxes', 'purchases', 'sales', 
            'product_parameters', 'product_categories', 'purchase_taxes', 
            'sale_taxes', 'product_stock', 'stock_prices', 'base_stock_sale_price', 
            'invoices', 'invoice_items', 'invoice_item_returns', 
            'invoice_histories', 'return_histories', 'pharmacies'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'business_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'categories', 'companies', 'farmulas', 'products', 'product_types', 
            'strengths', 'suppliers', 'taxes', 'purchases', 'sales', 
            'product_parameters', 'product_categories', 'purchase_taxes', 
            'sale_taxes', 'product_stock', 'stock_prices', 'base_stock_sale_price', 
            'invoices', 'invoice_items', 'invoice_item_returns', 
            'invoice_histories', 'return_histories', 'pharmacies'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'business_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropForeign(['business_id']);
                    $t->dropColumn('business_id');
                });
            }
        }
    }
}
