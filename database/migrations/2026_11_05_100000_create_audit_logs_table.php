<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who changed what, when (settings, prices, taxes, preferences, approvals, price overrides…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 60);                 // created | updated | deleted | price_override | …
            $table->string('subject_type')->nullable();   // morph class
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary')->nullable();        // human readable one-liner
            $table->json('changes')->nullable();          // {"field": {"from": x, "to": y}}
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
