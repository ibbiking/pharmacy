<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Separate super admin portal (docs/SUPER_ADMIN_PORTAL.md, P1): platform admins are their own table and guard, not a
 * role on `users`. Every existing super-admin user is copied over with the SAME password hash, so the owner's password
 * keeps working at /platform/login.
 *
 * platform_audit_logs is an append-only log (no updated_at / soft deletes on purpose): every business a platform admin
 * opens and every module switch / block is written once and never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->boolean('is_active')->default(true)->index();
            $t->timestamp('last_login_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
            $t->index('email');
        });

        Schema::create('platform_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('platform_admin_id')->nullable()->index();
            $t->string('action', 60);
            $t->unsignedBigInteger('business_id')->nullable()->index();
            $t->json('details')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });

        // Copy every super admin (role on users) into platform_admins, keeping the password hash.
        if (Schema::hasTable('roles') && Schema::hasTable('model_has_roles')) {
            $supers = DB::table('users')
                ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'super-admin')->where('model_has_roles.model_type', \App\Models\User::class)
                ->whereNull('users.deleted_at')
                ->get(['users.name', 'users.email', 'users.password']);
            foreach ($supers as $u) {
                $exists = DB::table('platform_admins')->whereRaw('lower(email) = ?', [strtolower($u->email)])->whereNull('deleted_at')->exists();
                if (! $exists) {
                    DB::table('platform_admins')->insert([
                        'name' => $u->name, 'email' => strtolower($u->email), 'password' => $u->password,
                        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_admins');
    }
};
