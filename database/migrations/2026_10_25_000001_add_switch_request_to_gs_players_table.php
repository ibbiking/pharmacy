<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSwitchRequestToGsPlayersTable extends Migration
{
    public function up()
    {
        Schema::table('gs_players', function (Blueprint $table) {
            // ID of the player who wants to take this slot
            $table->unsignedBigInteger('switch_request_from')->nullable()->after('last_seen_at');
            $table->timestamp('switch_requested_at')->nullable()->after('switch_request_from');
        });
    }

    public function down()
    {
        Schema::table('gs_players', function (Blueprint $table) {
            $table->dropColumn(['switch_request_from', 'switch_requested_at']);
        });
    }
}
