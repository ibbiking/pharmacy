<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 13f: composite indexes for the queries that grow with data.
 * - reports / dashboard: invoices by business + date; sold lines by business + product; returns by business + date
 * - POS lot allocation (LotAllocator): sellable lots per product, ordered by expiry (FEFO)
 * - expiry report / stock alerts: purchases by business + expiry
 * Indexes only; no data changes.
 */
return new class extends Migration
{
    private array $indexes = [
        'invoices' => [['business_id', 'created_at'], 'invoices_business_created_idx'],
        'invoice_items' => [['business_id', 'product_id'], 'invoice_items_business_product_idx'],
        'invoice_item_returns' => [['business_id', 'created_at'], 'item_returns_business_created_idx'],
        'base_stock_sale_price' => [['product_id', 'expiry_date', 'remaining_base_stock'], 'lots_product_expiry_remaining_idx'],
        'purchases' => [['business_id', 'expiry_date'], 'purchases_business_expiry_idx'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => [$columns, $name]) {
            if (Schema::hasTable($table) && ! Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => [, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
