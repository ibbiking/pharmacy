<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap step 10: chart of accounts and double-entry journals. Automatic journals point at their source
 * (source_type + source_id + source_event) and are replaced when the source changes, soft-deleted when it is reversed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('name', 150);
            $t->string('type', 20);      // asset, liability, equity, income, expense
            $t->string('sub_type', 30);  // cash, bank, receivable, inventory, current_asset, fixed_asset, payable, tax, current_liability, long_term_liability, equity, income, other_income, cost_of_sales, expense, other_expense
            $t->string('system_key', 40)->nullable();
            $t->string('description', 500)->nullable();
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'code']);
            $t->index(['business_id', 'system_key']);
        });

        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('number', 40)->nullable();
            $t->date('date');
            $t->string('memo', 500)->nullable();
            $t->string('source_type', 40)->nullable();
            $t->unsignedBigInteger('source_id')->nullable();
            $t->string('source_event', 30)->nullable();
            $t->boolean('is_manual')->default(false);
            $t->decimal('amount', 15, 2)->default(0);
            $t->foreignId('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'date']);
            $t->index(['business_id', 'source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_id');
            $t->decimal('debit', 15, 2)->default(0);
            $t->decimal('credit', 15, 2)->default(0);
            $t->string('description', 255)->nullable();
            $t->string('contact_type', 20)->nullable(); // customer | supplier | user
            $t->unsignedBigInteger('contact_id')->nullable();
            $t->date('date');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
