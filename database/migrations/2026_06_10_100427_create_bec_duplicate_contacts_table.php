<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBecDuplicateContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bec_duplicate_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('bec_contact_lists')->onDelete('cascade');
            $table->string('email');
            $table->json('row_data')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bec_duplicate_contacts');
    }
}
