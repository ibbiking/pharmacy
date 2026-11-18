<?php

use App\Services\Business\BusinessPreferenceDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Third "which batch sells first" option, "Any batch" (lot_selection:random, off by default):
 * gives every existing business the new preference row. Idempotent (ensure() skips rows that exist).
 */
return new class extends Migration
{
    public function up(): void
    {
        $defaults = app(BusinessPreferenceDefaults::class);
        DB::table('businesses')->whereNull('deleted_at')->orderBy('id')->pluck('id')->each(fn ($id) => $defaults->ensure((int) $id));
    }

    public function down(): void
    {
        DB::table('preferences')->where('type', 'lot_selection')->where('slug', 'random')->delete();
    }
};
