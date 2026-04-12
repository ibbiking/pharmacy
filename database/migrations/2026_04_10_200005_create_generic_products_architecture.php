<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGenericProductsArchitecture extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Generic Companies
        Schema::create('generic_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('approved'); // pending, approved, rejected
            $table->foreignId('suggested_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Generic Product Types
        Schema::create('generic_product_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('approved');
            $table->foreignId('suggested_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Generic Strengths
        Schema::create('generic_strengths', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('approved');
            $table->foreignId('suggested_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Generic Farmulas
        Schema::create('generic_farmulas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('approved');
            $table->foreignId('suggested_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Generic Categories (for packaging like Box, Strip, Tablet)
        Schema::create('generic_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('approved');
            $table->foreignId('suggested_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 6. Generic Products
        Schema::create('generic_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->unsignedBigInteger('generic_company_id');
            $table->foreign('generic_company_id')->references('id')->on('generic_companies')->onDelete('cascade');
            $table->string('farmula_id')->nullable(); // Using string because they store comma separated IDs
            $table->unsignedBigInteger('generic_product_type_id');
            $table->foreign('generic_product_type_id')->references('id')->on('generic_product_types')->onDelete('cascade');
            $table->string('strength_id')->nullable(); // Using string for comma separated IDs
            $table->string('rack')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('discount', 8, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('lock_max_discount')->default(false);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status')->default('approved');
            $table->foreignId('suggested_by_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Generic Product Parameters
        Schema::create('generic_product_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generic_product_id')->constrained('generic_products')->onDelete('cascade');
            $table->foreignId('generic_category_id')->constrained('generic_categories')->onDelete('cascade');
            $table->unsignedBigInteger('parent_generic_category_id');
            $table->foreign('parent_generic_category_id')->references('id')->on('generic_categories')->onDelete('cascade');
            $table->unsignedBigInteger('child_generic_category_id');
            $table->foreign('child_generic_category_id')->references('id')->on('generic_categories')->onDelete('cascade');
            $table->integer('quantity');
            // We omit price fields because generic products don't have business-specific prices
            $table->timestamps();
            $table->softDeletes();
        });

        // 8. Generic Product Categories
        Schema::create('generic_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generic_product_id')->constrained('generic_products')->onDelete('cascade');
            $table->unsignedBigInteger('parent_generic_category_id');
            $table->foreign('parent_generic_category_id')->references('id')->on('generic_categories')->onDelete('cascade');
            $table->unsignedBigInteger('child_generic_category_id')->nullable();
            $table->foreign('child_generic_category_id')->references('id')->on('generic_categories')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        // == Data Copy Phase ==

        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_companies (id, name, created_at, updated_at, deleted_at) SELECT id, name, created_at, updated_at, deleted_at FROM companies');
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_product_types (id, name, created_at, updated_at, deleted_at) SELECT id, name, created_at, updated_at, deleted_at FROM product_types');
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_strengths (id, name, created_at, updated_at, deleted_at) SELECT id, name, created_at, updated_at, deleted_at FROM strengths');
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_farmulas (id, name, created_at, updated_at, deleted_at) SELECT id, name, created_at, updated_at, deleted_at FROM farmulas');
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_categories (id, name, created_at, updated_at, deleted_at) SELECT id, name, created_at, updated_at, deleted_at FROM categories');

        // Copy products
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_products (id, product_name, generic_company_id, farmula_id, generic_product_type_id, strength_id, rack, barcode, discount, discount_percent, lock_max_discount, description, image, created_at, updated_at, deleted_at) SELECT id, product_name, company_id, farmula_id, product_type_id, strength_id, rack, barcode, discount, discount_percent, lock_max_discount, description, image, created_at, updated_at, deleted_at FROM products');

        // Copy product parameters
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_product_parameters (id, generic_product_id, generic_category_id, parent_generic_category_id, child_generic_category_id, quantity, created_at, updated_at, deleted_at) SELECT id, product_id, category_id, parent_category_id, child_category_id, quantity, created_at, updated_at, deleted_at FROM product_parameters');

        // Copy product categories
        \Illuminate\Support\Facades\DB::statement('INSERT INTO generic_product_categories (id, generic_product_id, parent_generic_category_id, child_generic_category_id, created_at, updated_at, deleted_at) SELECT id, product_id, parent_category_id, child_category_id, created_at, updated_at, deleted_at FROM product_categories');
        
        // Note: As per user instructions, we will manually truncate the original tables later after review!
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('generic_product_categories');
        Schema::dropIfExists('generic_product_parameters');
        Schema::dropIfExists('generic_products');
        Schema::dropIfExists('generic_categories');
        Schema::dropIfExists('generic_farmulas');
        Schema::dropIfExists('generic_strengths');
        Schema::dropIfExists('generic_product_types');
        Schema::dropIfExists('generic_companies');
    }
}
