<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap step 7: customers (Core), supplier terms, warehouses, transfers and adjustments (Inventory Pro).
 * Lot rows (`*_lots`) are append-only movement records of the transfer / adjustment they belong to (no soft deletes:
 * they are archived together with their document, which has soft deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('company', 150)->nullable();
            $t->string('email', 150)->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('address', 500)->nullable();
            $t->string('tax_number', 50)->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(0);
            $t->decimal('credit_limit', 15, 2)->nullable();
            $t->decimal('opening_balance', 15, 2)->default(0);
            $t->string('notes', 1000)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'name']);
        });

        Schema::table('suppliers', function (Blueprint $t) {
            $t->string('tax_number', 50)->nullable()->after('address');
            $t->unsignedSmallInteger('payment_terms_days')->default(0)->after('tax_number');
            $t->decimal('opening_balance', 15, 2)->default(0)->after('payment_terms_days');
        });

        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('code', 20)->nullable();
            $t->string('address', 500)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'is_default']);
        });

        Schema::table('base_stock_sale_price', function (Blueprint $t) {
            $t->foreignId('warehouse_id')->nullable()->after('product_id')->index();
        });
        Schema::table('purchases', function (Blueprint $t) {
            $t->foreignId('warehouse_id')->nullable()->after('supplier_id')->index();
        });

        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('number', 40);
            $t->foreignId('from_warehouse_id');
            $t->foreignId('to_warehouse_id');
            $t->date('date');
            $t->string('note', 500)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'date']);
        });
        Schema::create('stock_transfer_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id');
            $t->foreignId('category_id');
            $t->decimal('qty', 15, 4);
            $t->decimal('base_qty', 15, 4);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('stock_transfer_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_transfer_line_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_lot_id');
            $t->foreignId('to_lot_id');
            $t->decimal('base_qty', 15, 4);
            $t->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('number', 40);
            $t->foreignId('warehouse_id')->nullable();
            $t->date('date');
            $t->string('direction', 10); // increase | decrease
            $t->string('reason', 30);    // damaged, expired, lost, count, found, other
            $t->string('note', 500)->nullable();
            $t->decimal('value', 15, 2)->default(0); // cost value moved
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'date']);
        });
        Schema::create('stock_adjustment_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id');
            $t->foreignId('category_id');
            $t->decimal('qty', 15, 4);
            $t->decimal('base_qty', 15, 4);
            $t->decimal('value', 15, 2)->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('stock_adjustment_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('stock_adjustment_line_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lot_id');
            $t->foreignId('purchase_id')->nullable();
            $t->decimal('base_qty', 15, 4);
            $t->decimal('base_unit_cost', 15, 6)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lots');
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_lots');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn('warehouse_id'));
        Schema::table('base_stock_sale_price', fn (Blueprint $t) => $t->dropColumn('warehouse_id'));
        Schema::dropIfExists('warehouses');
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropColumn(['tax_number', 'payment_terms_days', 'opening_balance']));
        Schema::dropIfExists('customers');
    }
};
