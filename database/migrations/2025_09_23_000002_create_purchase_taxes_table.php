<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseTaxesTable extends Migration
{
    public function up()
    {
        Schema::create('purchase_taxes', function (Blueprint $table) {
            $table->id(); // same as bigIncrements

            // Match column types with parent tables
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('tax_id'); // because taxes table uses increments (INT)

            // Foreign key constraints
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('tax_id')->references('id')->on('taxes')->onDelete('cascade');

            $table->double('tax_rate')->nullable();
            $table->double('tax_amount')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_taxes');
    }
}