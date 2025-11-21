<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 50)->unique();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->enum('invoice_discount_type', ['percent', 'amount'])->default('amount');
            $table->decimal('invoice_discount_value', 10, 2)->default(0);
            $table->decimal('invoice_discount_amount', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0); // item-level total discount
            $table->decimal('total', 10, 2)->default(0); // before invoice discount
            $table->decimal('grand_total', 10, 2)->default(0); // after invoice discount
            $table->decimal('cash_received', 10, 2)->default(0);
            $table->decimal('change_return', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}