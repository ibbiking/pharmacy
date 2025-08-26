<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::disableForeignKeyConstraints();

        if (!Schema::hasColumn('categories', 'parent_category_id')){
            Schema::table('categories', function (Blueprint $table) {
                $table->integer('parent_category_id')->nullable()->after('name');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('categories', 'parent_category_id')){
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('parent_category_id');
            });
        }
    }
}
