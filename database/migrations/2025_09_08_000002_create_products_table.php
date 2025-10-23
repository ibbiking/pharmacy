<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->unsignedBigInteger('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->unsignedBigInteger('farmula_id');
            $table->foreign('farmula_id')->references('id')->on('farmulas')->onDelete('cascade');
            $table->unsignedBigInteger('product_type_id');
            $table->foreign('product_type_id')->references('id')->on('product_types')->onDelete('cascade');
            $table->unsignedBigInteger('strength_id');
            $table->foreign('strength_id')->references('id')->on('strengths')->onDelete('cascade');
            $table->unsignedBigInteger('sale_price_preference_id')->nullable();
            $table->foreign('sale_price_preference_id')->references('id')->on('preferences')->onDelete('cascade');
            $table->boolean('sale_price_including_tax')->default(0);
            $table->string('rack')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('discount', 8, 2)->default(0);
            $table->boolean('lock_max_discount')->default(false);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}