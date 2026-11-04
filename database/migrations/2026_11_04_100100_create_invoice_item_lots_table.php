<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which stock lots (base_stock_sale_price rows) each invoice line actually consumed, and at what
 * cost. Source of truth for: restocking returns to the exact lots, profit/loss cost of goods sold,
 * and (later) accounting COGS postings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_item_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->cascadeOnDelete();
            $table->unsignedBigInteger('base_stock_sale_price_id')->index(); // the lot
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->decimal('base_qty', 14, 4);
            $table->decimal('returned_base_qty', 14, 4)->default(0);
            $table->decimal('base_unit_cost', 14, 6)->default(0);          // paid cost per base unit
            $table->decimal('base_unit_purchase_tax', 14, 6)->default(0);  // purchase tax per base unit
            $table->decimal('base_unit_sale_price', 14, 6)->default(0);    // lot sale price per base unit
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_lots');
    }
};
