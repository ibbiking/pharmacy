<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGenericProductImportBatchesTable extends Migration
{
    public function up()
    {
        Schema::create('generic_product_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('product_ids');
            $table->enum('status', ['pending', 'processing', 'completed'])->default('pending');
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('generic_product_import_batches');
    }
}
