<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceBecSchemaV2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bec_email_templates', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('body');
        });

        Schema::table('bec_campaigns', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('from_name');
            $table->string('subject')->nullable()->change();
        });

        Schema::table('bec_contact_lists', function (Blueprint $table) {
            $table->integer('duplicate_rows')->default(0)->after('failed_rows');
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
            $table->dropColumn('attachments');
        });

        Schema::table('bec_campaigns', function (Blueprint $table) {
            $table->dropColumn('attachments');
            $table->string('subject')->nullable(false)->change();
        });

        Schema::table('bec_contact_lists', function (Blueprint $table) {
            $table->dropColumn('duplicate_rows');
        });
    }
}
