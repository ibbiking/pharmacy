<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('cascade');
            $table->double('unit_cost_price')->nullable();
            $table->double('total_cost_price')->nullable();
            $table->double('unit_cost_tax_amount')->nullable();
            $table->double('total_cost_tax_amount')->nullable();
            $table->double('quantity');
            $table->unsignedBigInteger('base_category_id');
            $table->foreign('base_category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->double('base_quantity');
            $table->double('unit_sale_price')->nullable();
            $table->double('total_sale_price')->nullable();
            $table->double('unit_sale_tax_amount')->nullable();
            $table->double('total_sale_tax_amount')->nullable();
            $table->double('base_unit_purchase_price')->nullable();
            $table->double('base_unit_purchase_tax_price')->nullable();
            $table->double('base_unit_total_purchase_tax_price')->nullable();
            $table->double('base_unit_sale_price')->nullable();
            $table->double('base_unit_sale_tax_price')->nullable();
            $table->double('base_unit_total_sale_tax_price')->nullable();
            $table->double('paid_unit_cost_price')->default(0);
            $table->double('extra_paid_per_unit')->default(0);
            $table->double('extra_paid_percent')->default(0);
            $table->double('paid_extra_total_cost_price')->default(0);
            $table->string('invoice_no')->nullable();
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
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
        Schema::dropIfExists('purchases');
    }
}
