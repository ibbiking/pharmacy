<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who uses the product, per day (owner 2026-11-20): one row per browser per day (site_visits) and one row per sign-in
 * (login_events). A browser is told apart by an anonymous random id in a long-lived cookie, plus its browser / OS /
 * device and IP. Read by the super admin's Visits page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $t) {
            $t->id();
            $t->uuid('visitor_id');
            $t->date('day');
            $t->foreignId('user_id')->nullable()->index();      // last signed-in account seen that day
            $t->boolean('is_demo')->default(false);
            $t->string('ip', 45)->nullable();
            $t->string('browser', 40)->nullable();
            $t->string('os', 40)->nullable();
            $t->string('device', 20)->nullable();              // desktop | mobile | tablet | bot
            $t->string('user_agent', 500)->nullable();
            $t->string('first_path', 255)->nullable();
            $t->string('referrer', 255)->nullable();
            $t->unsignedInteger('hits')->default(1);
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->unique(['visitor_id', 'day']);
            $t->index('day');
        });

        Schema::create('login_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('email', 191)->nullable();
            $t->uuid('visitor_id')->nullable()->index();
            $t->boolean('is_demo')->default(false);
            $t->string('ip', 45)->nullable();
            $t->string('browser', 40)->nullable();
            $t->string('os', 40)->nullable();
            $t->string('device', 20)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
        Schema::dropIfExists('site_visits');
    }
};
