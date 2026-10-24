<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGsChitsTable extends Migration
{
    public function up()
    {
        Schema::create('gs_chits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('round_id');
            $table->unsignedBigInteger('origin_player_id');
            $table->unsignedBigInteger('step1_player_id')->nullable();
            $table->unsignedBigInteger('step2_player_id')->nullable();
            $table->unsignedBigInteger('step3_player_id')->nullable();
            $table->string('name_val', 150)->nullable();
            $table->string('action_val', 255)->nullable();
            $table->string('location_val', 255)->nullable();
            $table->boolean('is_revealed')->default(false);
            $table->foreign('round_id')->references('id')->on('gs_rounds')->onDelete('cascade');
            $table->foreign('origin_player_id')->references('id')->on('gs_players')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gs_chits');
    }
}
