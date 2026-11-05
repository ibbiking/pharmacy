<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 4g: document numbers per business (INV-000001, RET-000001, … each business counts from its
 * own series), instead of the global row id. invoice_no becomes unique per business, not globally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('document_type', 40);  // invoice | return | quotation | sales_order | …
            $table->string('prefix', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'document_type']);
        });

        // New per-business unique key first (it can serve the old index's role), then drop the global one.
        Schema::table('invoices', fn (Blueprint $t) => $t->unique(['business_id', 'invoice_no'], 'invoices_business_invoice_no_unique'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropUnique('invoices_invoice_no_unique'));

        // Backfill: each existing business continues after the documents it already has. The service
        // also skips any number that already exists, so old formats can never collide.
        $now = now();
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $invoices = DB::table('invoices')->where('business_id', $businessId)->count();
            $returns = DB::table('invoice_item_returns')->where('business_id', $businessId)->distinct()->count('return_no');
            DB::table('number_series')->insert([
                ['business_id' => $businessId, 'document_type' => 'invoice', 'prefix' => 'INV-', 'next_number' => $invoices + 1, 'padding' => 6, 'created_at' => $now, 'updated_at' => $now],
                ['business_id' => $businessId, 'document_type' => 'return', 'prefix' => 'RET-', 'next_number' => $returns + 1, 'padding' => 6, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->unique('invoice_no'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropUnique('invoices_business_invoice_no_unique'));
        Schema::dropIfExists('number_series');
    }
};
