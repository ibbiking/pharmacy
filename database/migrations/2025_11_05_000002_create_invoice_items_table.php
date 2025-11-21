<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('name');
            $table->integer('qty');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('price_before_discount', 10, 2)->nullable();
            $table->enum('discount_type', ['percent', 'amount'])->default('percent');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('max_discount_percent', 10, 2)->nullable();
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->decimal('row_total', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_items');
    }
}