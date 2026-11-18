<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping for Wholesale & Distribution (owner's answer 11, docs/BUSINESS_TYPES_AND_SERVICES.md): simple packages /
 * shipments on a sales order or sales invoice (carrier, tracking number, packed → shipped → delivered, delivery note).
 * No courier API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_id')->index();          // the sales order or sales invoice shipped
            $t->string('number', 40);
            $t->string('carrier', 100)->nullable();
            $t->string('tracking_no', 100)->nullable();
            $t->unsignedInteger('packages')->default(1);
            $t->decimal('weight_kg', 10, 3)->nullable();
            $t->string('status', 20)->default('packed');    // packed | shipped | delivered
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->string('notes', 500)->nullable();
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
