<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFinancialMetricsToInvoiceItemReturns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('invoice_item_returns', function (Blueprint $table) {
            $table->decimal('unit_discount_deducted', 10, 2)->default(0)->after('qty_returned');
            $table->decimal('global_discount_clawback', 10, 2)->default(0)->after('unit_discount_deducted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('invoice_item_returns', function (Blueprint $table) {
            $table->dropColumn(['unit_discount_deducted', 'global_discount_clawback']);
        });
    }
}
