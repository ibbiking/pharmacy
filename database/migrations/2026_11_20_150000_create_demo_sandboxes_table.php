<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo installations (DEMO_INSTANCE=true): every visitor who signs in with the public demo login gets a private copy
 * of the three demo businesses (owner + accountant + cashier, same seeded data). Copies are built ahead of time
 * (status ready), handed to one browser (in_use), and deleted after an hour without activity (demo:cleanup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sandboxes', function (Blueprint $t) {
            $t->id();
            $t->string('status', 12)->default('building'); // building | ready | in_use
            $t->foreignId('owner_id')->nullable();
            $t->foreignId('accountant_id')->nullable();
            $t->foreignId('cashier_id')->nullable();
            $t->uuid('visitor_id')->nullable()->index();
            $t->string('ip', 45)->nullable();
            $t->string('browser', 40)->nullable();
            $t->string('os', 40)->nullable();
            $t->string('device', 20)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('last_seen_at')->nullable()->index();
            $t->timestamps();
            $t->index('status');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('demo_sandbox_id')->nullable()->index()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('demo_sandbox_id'));
        Schema::dropIfExists('demo_sandboxes');
    }
};
