<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 5.8a (I-5, I-7): each return row keeps what that return was (unit price, cash refund, taxes refunded), so
 * receipts and reprints come from the database instead of browser data or today's invoice totals. Quantities become
 * decimals (half a strip). Old rows are backfilled: price from their own invoice line, refund = line part − line
 * discount − invoice-discount share (their tax refund was not recorded; it stays null and the reprint says so).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_item_returns', function (Blueprint $table) {
            $table->decimal('qty_returned', 14, 4)->change();
            $table->decimal('price', 14, 4)->nullable()->after('qty_returned');
            $table->decimal('refund_amount', 14, 2)->nullable()->after('global_discount_clawback');
            $table->json('taxes')->nullable()->after('refund_amount');
        });

        DB::table('invoice_item_returns')->whereNull('price')->orderBy('id')->chunkById(500, function ($rows) {
            $prices = DB::table('invoice_items')->whereIn('id', $rows->pluck('invoice_item_id'))->pluck('price', 'id');
            foreach ($rows as $r) {
                $price = (float) ($prices[$r->invoice_item_id] ?? 0);
                DB::table('invoice_item_returns')->where('id', $r->id)->update([
                    'price' => $price,
                    'refund_amount' => round(max(0, $price * (float) $r->qty_returned - (float) $r->unit_discount_deducted - (float) $r->global_discount_clawback), 2),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_item_returns', function (Blueprint $table) {
            $table->dropColumn(['price', 'refund_amount', 'taxes']);
        });
        Schema::table('invoice_item_returns', function (Blueprint $table) {
            $table->integer('qty_returned')->change();
        });
    }
};
