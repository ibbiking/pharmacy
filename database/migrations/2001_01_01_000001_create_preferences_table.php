<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePreferencesTable extends Migration
{
    public function up(): void
    {
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->string('type');        // e.g. "sale_price"
            $table->string('slug');        // e.g. "static-price"
            $table->string('preference');  // e.g. "Static Sale Price"
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
}
