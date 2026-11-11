<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Roadmap step 11: expense categories (mapped to an expense account by code) and expenses with an approval flow. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->string('account_code', 20)->nullable(); // posts to this account of the chart (Accounting), else Miscellaneous
            $t->string('description', 500)->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('number', 40);
            $t->date('date');
            $t->foreignId('expense_category_id');
            $t->string('merchant', 150)->nullable();
            $t->string('description', 500)->nullable();
            $t->decimal('amount', 15, 2);      // total paid, tax included
            $t->foreignId('tax_id')->nullable();
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->string('paid_by', 10)->default('business'); // business | employee
            $t->string('payment_method', 10)->default('cash'); // cash | bank | card (business-paid)
            $t->string('receipt_path')->nullable();
            $t->string('status', 15)->default('draft'); // draft, submitted, approved, rejected, reimbursed
            $t->foreignId('user_id');                   // who claims it
            $t->foreignId('approved_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('rejection_reason', 500)->nullable();
            $t->foreignId('reimbursed_by')->nullable();
            $t->timestamp('reimbursed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['business_id', 'status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
