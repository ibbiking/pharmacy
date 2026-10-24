<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGsGamesTable extends Migration
{
    public function up()
    {
        Schema::create('gs_games', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 8)->unique();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('admin_player_id')->nullable();
            $table->enum('status', ['lobby', 'active', 'completed'])->default('lobby');
            $table->foreign('admin_id')->references('id')->on('gs_admins')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gs_games');
    }
}
