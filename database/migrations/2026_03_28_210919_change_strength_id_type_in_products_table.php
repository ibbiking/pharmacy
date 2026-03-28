<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeStrengthIdTypeInProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['strength_id']);
            });
        } catch (\Exception $e) {
            // Foreign key might not exist
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('strength_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('strength_id')->nullable()->change();
            $table->foreign('strength_id')->references('id')->on('strengths')->onDelete('cascade');
        });
    }
}
