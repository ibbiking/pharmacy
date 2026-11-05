<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 5.0 registration flow:
 *  - signup_requests.business_type: the type picked at sign-up, preselected in the business wizard.
 *  - password_resets.attempts: wrong-code counter; a code is cancelled after 5 wrong tries.
 * Both tables hold short-lived tokens (documented soft-delete exemption for signup_requests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signup_requests', function (Blueprint $table) {
            $table->string('business_type', 30)->nullable()->after('email');
        });
        Schema::table('password_resets', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('signup_requests', fn (Blueprint $table) => $table->dropColumn('business_type'));
        Schema::table('password_resets', fn (Blueprint $table) => $table->dropColumn('attempts'));
    }
};
