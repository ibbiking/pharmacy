<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleTaxBreakdownToInvoices extends Migration
{
    public function up()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('tax_amount', 10, 2)->default(0)->after('row_total');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tax_amount', 10, 2)->default(0)->after('grand_total');
        });

        // One row per distinct tax (named Sale Tax, or the "GST" markup derived
        // from Purchase.extra_paid_percent) attributed to a sold InvoiceItem.
        // tax_id is nullable because the GST/extra-paid bucket isn't backed by
        // a Tax record — it's the markup between unit_cost_price and
        // paid_unit_cost_price captured at purchase time.
        Schema::create('invoice_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->onDelete('cascade');
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->string('name');
            $table->decimal('rate', 8, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_item_taxes');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
}
