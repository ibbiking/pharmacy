<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGsAdminsTable extends Migration
{
    public function up()
    {
        Schema::create('gs_admins', function (Blueprint $table) {
            $table->id();
            $table->string('display_name', 100);
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gs_admins');
    }
}
