<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductStockTable extends Migration
{
    public function up()
    {
        Schema::create('product_stock', function (Blueprint $table) {
            $table->id(); // same as bigIncrements

            // Match column types with parent tables
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('base_category_id');

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('base_category_id')->references('id')->on('categories')->onDelete('cascade');

            $table->double('current_stock')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_stock');
    }
}