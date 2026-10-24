<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGsRoundsTable extends Migration
{
    public function up()
    {
        Schema::create('gs_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id');
            $table->unsignedTinyInteger('round_number')->default(1);
            $table->enum('gender_mode', ['male', 'female'])->default('male');
            $table->enum('status', ['step1', 'step2', 'step3', 'reveal', 'finished'])->default('step1');
            $table->foreign('game_id')->references('id')->on('gs_games')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gs_rounds');
    }
}
