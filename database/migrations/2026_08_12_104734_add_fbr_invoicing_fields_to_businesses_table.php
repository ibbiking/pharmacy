<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFbrInvoicingFieldsToBusinessesTable extends Migration
{
    public function up()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('invoice_source', 20)->default('local')->after('note');
            $table->string('fbr_business_name')->nullable()->after('invoice_source');
            $table->string('fbr_ntn')->nullable()->after('fbr_business_name');
            $table->string('fbr_strn')->nullable()->after('fbr_ntn');
            $table->string('fbr_pos_registration_no')->nullable()->after('fbr_strn');
            $table->string('fbr_environment', 20)->default('sandbox')->after('fbr_pos_registration_no');
            $table->text('fbr_api_token')->nullable()->after('fbr_environment');
            $table->timestamp('fbr_linked_at')->nullable()->after('fbr_api_token');
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_source',
                'fbr_business_name',
                'fbr_ntn',
                'fbr_strn',
                'fbr_pos_registration_no',
                'fbr_environment',
                'fbr_api_token',
                'fbr_linked_at',
            ]);
        });
    }
}
