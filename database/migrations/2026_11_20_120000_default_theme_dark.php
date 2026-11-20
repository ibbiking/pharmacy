<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dark mode is the default everywhere (owner 2026-11-20). The theme is each user's own choice (users.theme), never a
 * business or role setting. Until now every account started on "system", so those accounts move to dark; anyone who
 * prefers light or system picks it again with the switch in the header.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('theme', 10)->default('dark')->change());
        DB::table('users')->where('theme', 'system')->orWhereNull('theme')->update(['theme' => 'dark']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->string('theme', 10)->default('system')->change());
    }
};
