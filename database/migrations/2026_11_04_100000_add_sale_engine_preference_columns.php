<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sale engine (Roadmap step 2): columns for lot selection (FEFO/FIFO), POS price-change rules,
 * tax-inclusive pricing (owner-only tax) and server-authoritative pricing audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Business-wide preferences that carry a value (not just on/off).
        Schema::table('preferences', function (Blueprint $table) {
            $table->string('value')->nullable()->after('status');
        });

        // Per-product overrides. NULL = inherit the business-wide preference.
        Schema::table('products', function (Blueprint $table) {
            $table->string('lot_selection_mode', 10)->nullable()->after('sale_price_including_tax');
            $table->boolean('allow_price_change')->nullable()->after('lot_selection_mode');
            $table->decimal('min_price_percent', 5, 2)->nullable()->after('allow_price_change');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('lot_selection_mode', 10)->nullable()->after('sale_preference_slug');
            $table->decimal('server_unit_price', 14, 4)->nullable()->after('price');
            $table->boolean('price_overridden')->default(false)->after('server_unit_price');
            $table->decimal('included_tax_amount', 12, 2)->default(0)->after('tax_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('included_tax_amount', 12, 2)->default(0)->after('tax_amount');
        });

        // true = the tax is inside the price (owner-only visibility), false = added on top / GST disclosure.
        Schema::table('invoice_item_taxes', function (Blueprint $table) {
            $table->boolean('is_included')->default(false)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_item_taxes', fn (Blueprint $t) => $t->dropColumn('is_included'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('included_tax_amount'));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn([
            'lot_selection_mode', 'server_unit_price', 'price_overridden', 'included_tax_amount',
        ]));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn([
            'lot_selection_mode', 'allow_price_change', 'min_price_percent',
        ]));
        Schema::table('preferences', fn (Blueprint $t) => $t->dropColumn('value'));
    }
};
