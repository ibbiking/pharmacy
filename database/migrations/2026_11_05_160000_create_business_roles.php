<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap 4i: roles belong to a business; each role holds permission keys declared by the modules
 * (config/modules.php). Every business gets the built-in Owner and Sales person roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->string('system_key', 40)->nullable(); // owner | sales-person (built-in, not deletable)
            $table->json('limits')->nullable();           // {max_discount_percent}
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'system_key']);
        });

        // Join rows: replaced as a whole when the role is saved (the role itself is soft-deleted).
        Schema::create('business_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_role_id')->constrained('business_roles')->cascadeOnDelete();
            $table->string('permission', 100);
            $table->unique(['business_role_id', 'permission']);
        });

        Schema::table('business_user', function (Blueprint $table) {
            $table->foreignId('business_role_id')->nullable()->after('role')->constrained('business_roles')->nullOnDelete();
            $table->string('status', 20)->default('active')->after('business_role_id'); // active | suspended
        });

        $now = now();
        foreach (DB::table('businesses')->pluck('id') as $businessId) {
            $ownerId = DB::table('business_roles')->insertGetId([
                'business_id' => $businessId, 'name' => 'Owner', 'description' => 'Everything in this business.',
                'system_key' => 'owner', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $salesId = DB::table('business_roles')->insertGetId([
                'business_id' => $businessId, 'name' => 'Sales person', 'description' => 'Sells at the POS.',
                'system_key' => 'sales-person', 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (['pos.sell', 'pos.discount', 'pos.invoices.view'] as $permission) {
                DB::table('business_role_permissions')->insert(['business_role_id' => $salesId, 'permission' => $permission]);
            }
            DB::table('business_user')->where('business_id', $businessId)->where('role', 'owner')->update(['business_role_id' => $ownerId]);
            DB::table('business_user')->where('business_id', $businessId)->where('role', 'sales-person')->update(['business_role_id' => $salesId]);
        }
    }

    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_role_id');
            $table->dropColumn('status');
        });
        Schema::dropIfExists('business_role_permissions');
        Schema::dropIfExists('business_roles');
    }
};
