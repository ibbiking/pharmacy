<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FBR integration groundwork (docs/FBR_INTEGRATION.md §4, Roadmap step 6; POS Integration first).
 *  - fbr_settings: one row per business (regime, NTN/STRN, environment, encrypted token, offline policy, status).
 *  - fbr_terminals: the counters registered on IRIS (POS ID each).
 *  - fbr_submissions: every invoice / return / test sent to FBR, request and response with secrets stripped.
 *  - invoices.fbr_invoice_no / fbr_status: the number FBR issued (the local invoice_no stays the sale reference).
 * The old businesses.fbr_* columns are copied into fbr_settings and no longer read (kept so nothing is lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fbr_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('regime', 10)->default('pos');            // pos | di
            $t->string('ntn', 30)->nullable();                    // NTN or CNIC
            $t->string('strn', 30)->nullable();
            $t->string('business_name')->nullable();
            $t->string('province', 50)->nullable();
            $t->string('address', 500)->nullable();
            $t->string('environment', 20)->default('sandbox');   // sandbox | production
            $t->text('token')->nullable();                        // encrypted
            $t->string('offline_policy', 10)->default('block');  // block | retry
            $t->string('status', 20)->default('not_started');    // not_started | details_saved | sandbox_verified | live
            $t->timestamp('sandbox_verified_at')->nullable();
            $t->timestamp('live_at')->nullable();
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_error_at')->nullable();
            $t->string('last_error', 500)->nullable();
            $t->foreignId('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('fbr_terminals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('outlet_name', 120);
            $t->string('pos_id', 50);
            $t->string('environment', 20)->default('sandbox');
            $t->foreignId('warehouse_id')->nullable();             // the store whose till uses this POS ID (Warehouses)
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'environment']);
        });

        Schema::create('fbr_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10);                                // invoice | return | test
            $t->foreignId('invoice_id')->nullable()->index();
            $t->string('reference', 60);                           // our number (USIN): invoice no, return no, test ref
            $t->string('original_reference', 60)->nullable();      // the sale's invoice no on a return
            $t->string('environment', 20);
            $t->foreignId('terminal_id')->nullable();
            $t->string('status', 20)->default('pending');          // pending | sent | failed | manual_review
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->json('request')->nullable();                       // secrets stripped
            $t->json('response')->nullable();                      // secrets stripped
            $t->string('fbr_number', 100)->nullable();
            $t->string('qr_payload', 255)->nullable();
            $t->string('error', 500)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['business_id', 'kind', 'reference']);
            $t->index(['business_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $t) {
            $t->string('fbr_invoice_no', 100)->nullable()->after('invoice_no');
            $t->string('fbr_status', 20)->nullable()->after('fbr_invoice_no'); // null = not an FBR sale | pending | sent | failed | manual_review
        });

        // Carry over what the old Business settings screen stored (the token stays encrypted with the same app key).
        $now = now();
        foreach (DB::table('businesses')->where(fn ($q) => $q->whereNotNull('fbr_ntn')->orWhereNotNull('fbr_strn')->orWhereNotNull('fbr_pos_registration_no'))->get() as $b) {
            DB::table('fbr_settings')->insert([
                'business_id' => $b->id, 'regime' => 'pos', 'ntn' => $b->fbr_ntn, 'strn' => $b->fbr_strn,
                'business_name' => $b->fbr_business_name, 'environment' => $b->fbr_environment ?: 'sandbox',
                'token' => $b->fbr_api_token, 'status' => 'details_saved', 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($b->fbr_pos_registration_no) {
                DB::table('fbr_terminals')->insert(['business_id' => $b->id, 'outlet_name' => 'Main counter', 'pos_id' => $b->fbr_pos_registration_no,
                    'environment' => $b->fbr_environment ?: 'sandbox', 'is_default' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        // Nobody may be on FBR numbering without a verified, live link (the old stub never issued real numbers).
        DB::table('businesses')->where('invoice_source', 'fbr')->update(['invoice_source' => 'local']);
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['fbr_invoice_no', 'fbr_status']));
        Schema::dropIfExists('fbr_submissions');
        Schema::dropIfExists('fbr_terminals');
        Schema::dropIfExists('fbr_settings');
    }
};
