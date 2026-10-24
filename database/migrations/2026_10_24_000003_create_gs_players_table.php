<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGsPlayersTable extends Migration
{
    public function up()
    {
        Schema::create('gs_players', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id');
            $table->string('name', 100);
            $table->string('session_token', 64)->unique();
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreign('game_id')->references('id')->on('gs_games')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gs_players');
    }
}
