<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit in every business type (docs/BUSINESS_TYPES_AND_SERVICES.md, phases 3–4):
 *  - invoices (POS sales): the saved customer and whether the sale is paid / partly paid / on credit;
 *  - purchases: whether the supplier was paid / partly paid / is owed (paid amount at entry);
 *  - documents: "origin" marks a document created in the background for a POS sale, a POS return or a purchase
 *    (indirect invoice / credit note / bill). Such a document never moves stock and never posts its own journal:
 *    the POS sale / return / purchase already did both. invoice_id / purchase_id point at the source.
 * Existing rows: invoices are paid cash sales, purchases were paid (the old behaviour), documents have no origin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $t) {
            $t->foreignId('customer_id')->nullable()->after('id')->index();
            $t->string('payment_status', 10)->default('paid')->after('change_return'); // paid | partial | unpaid
        });
        Schema::table('purchases', function (Blueprint $t) {
            $t->string('payment_status', 10)->default('paid')->after('supplier_id'); // paid | partial | unpaid
            $t->decimal('amount_paid', 15, 2)->nullable()->after('payment_status');
        });
        Schema::table('documents', function (Blueprint $t) {
            $t->string('origin', 20)->nullable()->after('type');       // null = entered on its own; pos | pos_return | purchase
            $t->foreignId('invoice_id')->nullable()->after('source_id')->index();
            $t->foreignId('purchase_id')->nullable()->after('invoice_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropIndex(['invoice_id']);
            $t->dropIndex(['purchase_id']);
            $t->dropColumn(['origin', 'invoice_id', 'purchase_id']);
        });
        Schema::table('purchases', function (Blueprint $t) {
            $t->dropColumn(['payment_status', 'amount_paid']);
        });
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropIndex(['customer_id']);
            $t->dropColumn(['customer_id', 'payment_status']);
        });
    }
};
