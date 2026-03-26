<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeFarmulaIdTypeInProductsTable extends Migration
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
                $table->dropForeign(['farmula_id']);
            });
        } catch (\Exception $e) {
            // Foreign key might not exist, proceed
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('farmula_id')->nullable()->change();
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
            $table->unsignedBigInteger('farmula_id')->nullable()->change();
            $table->foreign('farmula_id')->references('id')->on('farmulas')->onDelete('cascade');
        });
    }
}
