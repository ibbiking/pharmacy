<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap step 4f: each business sends email through its own SMTP / Mailgun account, in a branded
 * layout (logo + brand colour). Secrets are stored encrypted (see BusinessMailSetting casts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('name');
            $table->string('brand_color', 7)->nullable()->after('logo');
        });

        Schema::create('business_mail_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('driver', 20)->default('platform'); // platform | smtp | mailgun
            $table->string('from_name')->nullable();
            $table->string('from_address')->nullable();
            $table->string('reply_to')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 10)->nullable(); // tls | ssl | none
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();         // encrypted
            $table->string('mailgun_domain')->nullable();
            $table->text('mailgun_secret')->nullable();        // encrypted
            $table->string('mailgun_endpoint')->nullable();    // api.mailgun.net | api.eu.mailgun.net
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_mail_settings');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['logo', 'brand_color']);
        });
    }
};
