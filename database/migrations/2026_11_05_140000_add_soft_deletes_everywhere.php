<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 4j: every business table can be archived (soft delete) instead of hard deleted.
 *
 * Exempt by design: framework tables (migrations, jobs, job_batches, failed_jobs, permission pivots),
 * short-lived records (signup_requests, pos_cart_reservations), platform_settings (key/value) and
 * audit_logs (append-only).
 */
return new class extends Migration
{
    private const TABLES = [
        'businesses', 'currencies', 'pharmacies', 'business_modules', 'business_mail_settings', 'module_prices',
        'invoice_item_lots', 'invoice_item_returns', 'invoice_item_taxes', 'invoice_histories', 'return_histories',
        'generic_product_import_batches',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
            }
        }

        // currencies: one LIVE (business, code) pair. Archived rows must not block re-adding the code.
        // MySQL treats NULLs as distinct, so the unique key uses a generated "active" flag
        // (1 while live, NULL once archived) instead of deleted_at itself.
        // The new index is created BEFORE dropping the old one: the business_id foreign key needs an
        // index that starts with business_id, and MySQL refuses to drop the only one it has.
        DB::statement('ALTER TABLE currencies ADD active_key TINYINT AS (IF(deleted_at IS NULL, 1, NULL)) STORED');
        Schema::table('currencies', function (Blueprint $t) {
            $t->unique(['business_id', 'currency_code', 'active_key'], 'currencies_live_code_unique');
        });
        Schema::table('currencies', function (Blueprint $t) {
            $t->dropUnique('currencies_business_id_currency_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', fn (Blueprint $t) => $t->unique(['business_id', 'currency_code']));
        Schema::table('currencies', function (Blueprint $t) {
            $t->dropUnique('currencies_live_code_unique');
            $t->dropColumn('active_key');
        });

        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
            }
        }
    }
};
