<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap step 4d: what the platform sells.
 *  - module_prices: price of each module (à-la-carte / custom plans)
 *  - plans: named bundles of modules with their own price and limits
 *  - platform_settings: platform-wide switches, e.g. "Free for everyone" (launch pricing, ON)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_prices', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40)->unique();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->json('modules');                 // module keys (core is implied)
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('limits')->nullable();      // {users, businesses, products, invoices_per_month, warehouses}; null = unlimited
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_monthly_id')->nullable();
            $table->string('stripe_price_yearly_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Launch pricing: everything free until the super admin switches it off.
        DB::table('platform_settings')->insert([
            ['key' => 'free_for_everyone', 'value' => json_encode(true), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'free_grace_until', 'value' => json_encode(null), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('module_prices');
    }
};
