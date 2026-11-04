<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap step 4b: business type + the modules each business has switched on.
 * Existing businesses are pharmacies using core + stock + POS + pharmacy pack (exactly what they
 * have today), switched on for free (launch pricing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type', 20)->default('pharmacy')->after('name');
        });

        Schema::create('business_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('module', 40);
            $table->string('status', 20)->default('active'); // active | past_due | suspended
            $table->string('source', 20)->default('free');   // free | plan | manual | trial
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'module']);
        });

        $now = now();
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            foreach (['core', 'stock', 'pos', 'pharmacy'] as $module) {
                DB::table('business_modules')->insertOrIgnore([
                    'business_id' => $businessId, 'module' => $module, 'status' => 'active', 'source' => 'free',
                    'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_modules');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
