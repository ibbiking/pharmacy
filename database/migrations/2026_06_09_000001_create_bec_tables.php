<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBecTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Contact Lists
        Schema::create('bec_contact_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('total_rows')->default(0);
            $table->integer('processed_rows')->default(0);
            $table->integer('failed_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        // 2. Contact List Columns
        Schema::create('bec_contact_list_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('bec_contact_lists')->onDelete('cascade');
            $table->string('column_name'); // e.g. first_name
            $table->string('ui_label');   // e.g. First Name
            $table->timestamps();
        });

        // 3. Contacts
        Schema::create('bec_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('bec_contact_lists')->onDelete('cascade');
            $table->string('email');
            $table->json('data'); // Dynamic columns storage
            $table->enum('status', ['enabled', 'disabled'])->default('enabled');
            $table->timestamps();
            
            $table->unique(['contact_list_id', 'email']);
        });

        // 4. Email Templates
        Schema::create('bec_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->longText('body'); // HTML content
            $table->timestamps();
        });

        // 5. Signatures
        Schema::create('bec_signatures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('logo')->nullable();
            $table->json('social_links')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // 6. SMTP Settings
        Schema::create('bec_smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->integer('port');
            $table->string('username');
            $table->text('password'); // Encrypted
            $table->string('encryption')->default('tls');
            $table->string('from_email');
            $table->string('from_name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 7. Campaigns
        Schema::create('bec_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('contact_list_id')->constrained('bec_contact_lists');
            $table->foreignId('template_id')->constrained('bec_email_templates');
            $table->foreignId('signature_id')->nullable()->constrained('bec_signatures');
            $table->string('subject');
            $table->timestamp('scheduled_at')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'processing', 'completed', 'failed', 'cancelled'])->default('draft');
            $table->timestamps();
        });

        // 8. Campaign Logs (Tracking)
        Schema::create('bec_campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('bec_campaigns')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('bec_contacts');
            $table->string('email');
            $table->enum('status', ['sent', 'failed', 'delivered', 'opened', 'clicked'])->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->string('tracking_id')->unique();
            $table->timestamps();
        });

        // 9. Activity Logs
        Schema::create('bec_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bec_activity_logs');
        Schema::dropIfExists('bec_campaign_logs');
        Schema::dropIfExists('bec_campaigns');
        Schema::dropIfExists('bec_smtp_settings');
        Schema::dropIfExists('bec_signatures');
        Schema::dropIfExists('bec_email_templates');
        Schema::dropIfExists('bec_contacts');
        Schema::dropIfExists('bec_contact_list_columns');
        Schema::dropIfExists('bec_contact_lists');
    }
}
