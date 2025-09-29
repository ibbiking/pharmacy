<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockPricesTable extends Migration
{
    public function up()
    {
        Schema::create('stock_prices', function (Blueprint $table) {
            $table->id(); // same as bigIncrements

            // Match column types with parent tables
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('base_category_id');

            // Foreign key constraints
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('base_category_id')->references('id')->on('categories')->onDelete('cascade');

            $table->double('base_stock')->nullable();
            $table->double('category_stock')->nullable();
            $table->double('category_unit_purchase_price')->nullable();
            $table->double('category_unit_purchase_tax_price')->nullable();
            $table->double('category_unit_total_purchase_tax_price')->nullable();
            $table->double('category_unit_sale_price')->nullable();
            $table->double('category_unit_sale_tax_price')->nullable();
            $table->double('category_unit_total_sale_tax_price')->nullable();
            $table->double('base_category_unit_purchase_price')->nullable();
            $table->double('base_category_unit_purchase_tax_price')->nullable();
            $table->double('base_category_unit_total_purchase_tax_price')->nullable();
            $table->double('base_category_unit_sale_price')->nullable();
            $table->double('base_category_unit_sale_tax_price')->nullable();
            $table->double('base_category_unit_total_sale_tax_price')->nullable();
            $table->date('expiry_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_prices');
    }
}