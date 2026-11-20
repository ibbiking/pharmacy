<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent record of demo visitors (owner 2026-11-20): one row per demo session (browser id, browser / OS / device,
 * IP, when, how much they did) and one row per action they took (what they saved, changed, deleted or were refused).
 * Kept when the private demo copy itself is deleted, for the super admin's Visits page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sessions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sandbox_id')->nullable()->index();
            $t->uuid('visitor_id')->nullable()->index();
            $t->string('role', 12)->nullable();               // owner | accountant | cashier (first sign-in)
            $t->string('ip', 45)->nullable();
            $t->string('browser', 40)->nullable();
            $t->string('os', 40)->nullable();
            $t->string('device', 20)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->unsignedInteger('page_views')->default(0);
            $t->unsignedInteger('actions')->default(0);
            $t->timestamp('started_at')->nullable()->index();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamp('ended_at')->nullable();             // its copy was deleted
        });
        Schema::create('demo_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('demo_session_id')->index();
            $t->string('method', 8);
            $t->string('route', 120)->nullable();
            $t->string('path', 255)->nullable();
            $t->unsignedSmallInteger('status')->nullable();
            $t->boolean('refused')->default(false);            // stopped by the demo guard
            $t->timestamp('created_at')->nullable();
        });
        Schema::table('demo_sandboxes', fn (Blueprint $t) => $t->unsignedBigInteger('demo_session_id')->nullable()->after('cashier_id'));
    }

    public function down(): void
    {
        Schema::table('demo_sandboxes', fn (Blueprint $t) => $t->dropColumn('demo_session_id'));
        Schema::dropIfExists('demo_activities');
        Schema::dropIfExists('demo_sessions');
    }
};
