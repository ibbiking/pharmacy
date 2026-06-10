<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddContactListIdToTemplatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bec_email_templates', function (Blueprint $table) {
            $table->foreignId('contact_list_id')->nullable()->constrained('bec_contact_lists')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bec_email_templates', function (Blueprint $table) {
            $table->dropForeign(['contact_list_id']);
            $table->dropColumn('contact_list_id');
        });
    }
}
