<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap step 4e: Stripe billing.
 *  - businesses.stripe_customer_id: the business is the paying customer (not the user).
 *  - subscriptions: one row per Stripe subscription of a business (plan or custom set of modules).
 *  - billing_invoices: "bill of payment" copies of Stripe invoices, shown on the Billing page.
 *  - stripe_events: webhook inbox, for idempotency and troubleshooting. Append-only like audit_logs:
 *    rows are never edited by people or deleted, so it has timestamps but no soft deletes (documented
 *    exemption in tests/Feature/Platform/SoftDeletesTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('currency_id')->index();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('modules');                       // module keys paid for (dependencies included)
            $table->json('items')->nullable();             // [{type: plan|module, key, price_id, item_id}]
            $table->string('interval', 10)->default('monthly');
            // incomplete → (trialing) → active → past_due → canceled | unpaid | incomplete_expired
            $table->string('status', 30)->default('incomplete');
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->string('stripe_checkout_session_id')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0); // per interval
            $table->string('currency', 3)->default('USD');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'status']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_invoice_id')->unique();
            $table->string('number')->nullable();
            $table->string('status', 20);                  // draft, open, paid, uncollectible, void
            $table->decimal('amount_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('hosted_invoice_url', 1000)->nullable();
            $table->string('invoice_pdf', 1000)->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stripe_events', function (Blueprint $table) {
            $table->id();
            $table->string('stripe_event_id')->unique();
            $table->string('type', 100)->index();
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending, processed, ignored, failed
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
    }
};
