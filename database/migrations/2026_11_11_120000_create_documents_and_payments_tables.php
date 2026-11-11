<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap steps 8–9: one document engine for B2B sales and purchasing.
 * documents.type: quotation, sales_order, sales_invoice, credit_note (sales); purchase_order, goods_receipt, bill,
 * vendor_credit (purchasing). balance = what is still open (invoice/bill: unpaid; credit: not applied yet).
 * document_line_lots: the batches a sales invoice took (restocked exactly by a credit note) and the batches a vendor
 * credit returned to the supplier (append-only movement rows, archived with their document).
 * payment_allocations: which documents a payment (or a credit note / vendor credit) settled (append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);
            $t->string('number', 40);
            $t->foreignId('customer_id')->nullable()->index();
            $t->foreignId('supplier_id')->nullable()->index();
            $t->foreignId('warehouse_id')->nullable();
            $t->foreignId('source_id')->nullable()->index(); // converted from (quote → order → invoice, PO → receipt/bill, invoice → credit note)
            $t->date('date');
            $t->date('due_date')->nullable();
            $t->string('reference', 100)->nullable();
            $t->string('status', 20)->default('draft');
            $t->decimal('subtotal', 15, 2)->default(0);
            $t->decimal('discount_total', 15, 2)->default(0);
            $t->decimal('tax_total', 15, 2)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->decimal('paid', 15, 2)->default(0);
            $t->decimal('balance', 15, 2)->default(0);
            $t->decimal('cost_total', 15, 2)->default(0); // COGS of an invoice / value of goods moved
            $t->string('notes', 1000)->nullable();
            $t->string('terms', 1000)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'type', 'date']);
            $t->index(['business_id', 'number']);
        });

        Schema::create('document_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable();
            $t->foreignId('category_id')->nullable();
            $t->foreignId('source_line_id')->nullable();
            $t->string('description', 255)->nullable();
            $t->decimal('qty', 15, 4);
            $t->decimal('base_qty', 15, 4)->default(0);
            $t->decimal('unit_price', 15, 4)->default(0);
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->decimal('discount_amount', 15, 2)->default(0);
            $t->foreignId('tax_id')->nullable();
            $t->decimal('tax_rate', 6, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('net_amount', 15, 2)->default(0);  // after discount, before tax
            $t->decimal('line_total', 15, 2)->default(0);  // net + tax
            $t->decimal('done_qty', 15, 4)->default(0);    // received (PO) / invoiced (SO) / credited (invoice)
            $t->decimal('cost_amount', 15, 2)->default(0);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('document_line_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_line_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lot_id');
            $t->foreignId('purchase_id')->nullable()->index();
            $t->decimal('base_qty', 15, 4);
            $t->decimal('returned_base_qty', 15, 4)->default(0);
            $t->decimal('base_unit_cost', 15, 6)->default(0);
            $t->timestamps();
        });

        Schema::table('purchases', function (Blueprint $t) {
            $t->foreignId('document_id')->nullable()->after('warehouse_id')->index(); // goods received note that created this batch
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('direction', 10); // in (from a customer) | out (to a supplier)
            $t->string('number', 40);
            $t->foreignId('customer_id')->nullable()->index();
            $t->foreignId('supplier_id')->nullable()->index();
            $t->date('date');
            $t->decimal('amount', 15, 2);
            $t->decimal('unapplied', 15, 2)->default(0);
            $t->string('method', 20)->default('cash'); // cash, bank, card, cheque, other
            $t->string('reference', 100)->nullable();
            $t->string('notes', 500)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'direction', 'date']);
        });

        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->nullable()->index();
            $t->foreignId('credit_document_id')->nullable()->index(); // a credit note / vendor credit applied instead of money
            $t->foreignId('document_id')->index();
            $t->decimal('amount', 15, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn('document_id'));
        Schema::dropIfExists('document_line_lots');
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
    }
};
