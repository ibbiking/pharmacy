<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 5.6a (G-4): bulk imports record per-item failures instead of rolling the whole batch back, and remember when
 * processing started so the safety sweep can release a batch whose worker died.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generic_product_import_batches', function (Blueprint $table) {
            $table->unsignedInteger('failed_count')->default(0)->after('skipped_count');
            $table->text('last_error')->nullable()->after('failed_count');
            $table->timestamp('started_at')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('generic_product_import_batches', function (Blueprint $table) {
            $table->dropColumn(['failed_count', 'last_error', 'started_at']);
        });
    }
};
