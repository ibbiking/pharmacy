<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrenciesTable extends Migration
{
    public function up()
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            // NULL = global currency, visible to every business. Set = a
            // business's own custom currency, visible only to that business.
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->string('currency_code', 10);
            $table->string('name');
            $table->string('symbol', 20);
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->timestamps();

            $table->unique(['business_id', 'currency_code']);
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('currency_id')->nullable()->after('fbr_linked_at')->constrained('currencies')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('currency_id');
        });

        Schema::dropIfExists('currencies');
    }
}
