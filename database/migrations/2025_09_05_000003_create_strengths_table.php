<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStrengthsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('strengths', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "500mg", "250ml", etc.
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('strengths');
    }
}