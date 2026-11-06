<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Roadmap 13d: per-business data exports (a zip of CSVs, kept 7 days). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->index();
            $table->foreignId('requested_by')->nullable();
            $table->string('status', 20)->default('pending'); // pending | running | done | failed
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('tables')->nullable();
            $table->unsignedBigInteger('rows')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_exports');
    }
};
