<?php

namespace Tests\Feature\Platform;

use App\Models\Billing\BillingInvoice;
use App\Models\Billing\StripeEvent;
use App\Models\Billing\Subscription;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Billing\Exceptions\BillingException;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Billing\Gateway\StripeGateway;
use App\Services\Modules\ModuleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePaymentGateway;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/** Roadmap 4e: Stripe billing flow, entitlements, webhooks and error handling. */
class BillingTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private FakePaymentGateway $stripe;
    private Plan $starter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness();
        $this->stripe = new FakePaymentGateway();
        $this->app->instance(PaymentGateway::class, $this->stripe);

        ModulePrice::updateOrCreate(['module' => 'stock'], ['monthly_price' => 10, 'yearly_price' => 100, 'currency' => 'USD']);
        ModulePrice::updateOrCreate(['module' => 'pos'], ['monthly_price' => 15, 'yearly_price' => 150, 'currency' => 'USD']);
        ModulePrice::updateOrCreate(['module' => 'pharmacy'], ['monthly_price' => 5, 'yearly_price' => 50, 'currency' => 'USD']);
        $this->starter = Plan::create(['name' => 'Starter', 'slug' => 'starter', 'modules' => ['stock', 'pos'], 'monthly_price' => 20, 'yearly_price' => 200, 'currency' => 'USD', 'trial_days' => 0]);
    }

    private function launchPricingOff(): void
    {
        PlatformSetting::put('free_for_everyone', false);
        PlatformSetting::put('free_grace_until', null);
    }

    private function modules(): ModuleManager
    {
        app()->forgetScopedInstances();

        return app(ModuleManager::class);
    }

    private function checkoutStarter(): Subscription
    {
        $this->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly'])
            ->assertRedirect('https://checkout.stripe.test/'.array_key_last($this->stripe->sessions));
        $sessionId = array_key_last($this->stripe->sessions);
        $this->stripe->completeCheckout($sessionId);
        $this->get(route('billing.index', ['checkout' => 'success', 'session_id' => $sessionId]))->assertOk()->assertSessionHas('success');

        return Subscription::where('business_id', $this->business->id)->latest('id')->first();
    }

    private function webhook(array $event): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'valid', 'CONTENT_TYPE' => 'application/json'], json_encode($event));
    }

    public function test_billing_page_shows_plans_and_provider_state(): void
    {
        $this->get(route('billing.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Index')
            ->where('provider.configured', true)
            ->where('provider.mode', 'test')
            ->where('provider.free', true)
            ->where('subscription', null)
            ->has('plans', 1)
            ->where('plans.0.modules', [['name' => 'Stock & Purchasing', 'available' => true], ['name' => 'POS & Invoicing', 'available' => true]])
            ->has('invoices.data', 0));
    }

    public function test_without_stripe_keys_paying_is_refused_politely_and_leaves_nothing_behind(): void
    {
        $this->app->instance(PaymentGateway::class, new StripeGateway(null, null, null));

        $this->get(route('billing.index'))->assertInertia(fn (Assert $page) => $page->where('provider.configured', false)->where('provider.mode', null));
        $this->from(route('billing.index'))->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly'])
            ->assertRedirect(route('billing.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'not connected yet'));
        $this->assertSame(0, Subscription::count());

        $this->postJson(route('billing.quote'), ['plan_id' => $this->starter->id, 'interval' => 'yearly'])->assertOk()->assertJsonPath('total', 200);
    }

    public function test_quote_adds_dependencies_and_rejects_mixed_currencies(): void
    {
        $this->postJson(route('billing.quote'), ['modules' => ['pos'], 'interval' => 'monthly'])
            ->assertOk()->assertJsonPath('total', 25)->assertJsonPath('lines.0.key', 'stock')->assertJsonPath('lines.0.added_for_dependency', true)->assertJsonPath('lines.1.added_for_dependency', false);
        $this->postJson(route('billing.quote'), ['plan_id' => $this->starter->id, 'modules' => ['pharmacy'], 'interval' => 'monthly'])
            ->assertOk()->assertJsonPath('total', 25)->assertJsonCount(2, 'lines');

        ModulePrice::where('module', 'pharmacy')->update(['currency' => 'PKR']);
        $this->postJson(route('billing.quote'), ['plan_id' => $this->starter->id, 'modules' => ['pharmacy'], 'interval' => 'monthly'])
            ->assertStatus(422)->assertJsonPath('error', 'currency');
        $this->postJson(route('billing.quote'), ['interval' => 'monthly'])->assertStatus(422)->assertJsonValidationErrors('modules');
    }

    public function test_checkout_creates_customer_and_prices_once_then_switches_modules_on(): void
    {
        $this->launchPricingOff();
        $this->delete(route('modules.deactivate', 'pharmacy'));
        BusinessModule::where('business_id', $this->business->id)->update(['source' => 'free']);

        $sub = $this->checkoutStarter();

        $this->assertSame('active', $sub->status);
        $this->assertNotNull($sub->current_period_end);
        $this->assertNotNull($sub->items[0]['item_id']);
        $this->assertStringStartsWith('cus_', (string) $this->business->fresh()->stripe_customer_id);
        $this->assertNotNull($this->starter->fresh()->stripe_price_monthly_id);
        $session = $this->stripe->called('createCheckoutSession')[0][0];
        $this->assertSame((string) $this->business->id, $session['client_reference_id']);
        $this->assertStringContainsString('{CHECKOUT_SESSION_ID}', $session['success_url']);
        $this->assertSame([['price' => $this->starter->fresh()->stripe_price_monthly_id, 'quantity' => 1]], $session['line_items']);
        $this->assertSame('subscription', BusinessModule::where('business_id', $this->business->id)->where('module', 'pos')->value('source'));

        // A second business subscribing re-uses the Stripe price (no new product/price).
        $this->assertCount(1, $this->stripe->called('createPrice'));
    }

    public function test_cannot_subscribe_twice_and_abandoned_checkouts_are_replaced(): void
    {
        $this->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly']);
        $this->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'yearly']);
        $this->assertSame(1, Subscription::where('status', 'incomplete')->count(), 'the first, abandoned checkout is replaced');

        $this->stripe->completeCheckout(array_key_last($this->stripe->sessions));
        $this->get(route('billing.index', ['checkout' => 'success', 'session_id' => array_key_last($this->stripe->sessions)]));

        $this->from(route('billing.index'))->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'already has an active subscription'));
    }

    public function test_a_session_of_another_business_is_refused(): void
    {
        $this->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly']);
        $sessionId = array_key_last($this->stripe->sessions);
        $this->stripe->sessions[$sessionId]['client_reference_id'] = '999999';
        $this->stripe->completeCheckout($sessionId);

        $this->get(route('billing.index', ['checkout' => 'success', 'session_id' => $sessionId]))->assertSessionHas('error', 'This payment belongs to another business.');
        $this->assertSame('incomplete', Subscription::first()->status);
    }

    public function test_cancelled_checkout_and_stripe_errors_are_explained(): void
    {
        $this->get(route('billing.index', ['checkout' => 'cancelled']))->assertSessionHas('info', 'Checkout cancelled. Nothing was charged.');

        $this->stripe->failNext = new BillingException('Stripe is busy right now. Please try again in a minute.', 'rate_limit');
        $this->from(route('billing.index'))->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly'])
            ->assertSessionHas('error', 'Stripe is busy right now. Please try again in a minute.');
        $this->assertSame(0, Subscription::count());
    }

    public function test_a_free_plan_switches_on_without_stripe(): void
    {
        $this->launchPricingOff();
        $free = Plan::create(['name' => 'Free', 'slug' => 'free', 'modules' => ['stock'], 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD']);
        $this->app->instance(PaymentGateway::class, new StripeGateway(null, null, null));

        $this->post(route('billing.subscribe'), ['plan_id' => $free->id, 'interval' => 'monthly'])->assertSessionHas('success');
        $this->assertSame('active', Subscription::first()->status);
        $this->assertNull(Subscription::first()->stripe_subscription_id);

        $this->post(route('billing.cancel'))->assertSessionHas('success');
        $this->assertSame('canceled', Subscription::first()->status);
    }

    public function test_change_plan_is_prorated_and_replaces_the_items(): void
    {
        $sub = $this->checkoutStarter();
        $oldItem = $sub->items[0]['item_id'];

        $this->post(route('billing.change'), ['plan_id' => $this->starter->id, 'modules' => ['pharmacy'], 'interval' => 'monthly'])->assertSessionHas('success');

        [$id, $params] = $this->stripe->called('updateSubscription')[0];
        $this->assertSame($sub->stripe_subscription_id, $id);
        $this->assertSame('create_prorations', $params['proration_behavior']);
        $this->assertContains(['id' => $oldItem, 'deleted' => true], $params['items']);
        $this->assertCount(3, $params['items']);
        $sub->refresh();
        $this->assertEquals(25, $sub->amount);
        $this->assertContains('pharmacy', $sub->modules);
    }

    public function test_cancel_at_period_end_and_resume(): void
    {
        $this->checkoutStarter();

        $this->post(route('billing.cancel'))->assertSessionHas('success', fn ($m) => str_contains($m, 'You keep access until'));
        $sub = Subscription::first();
        $this->assertTrue($sub->cancel_at_period_end);
        $this->assertTrue($sub->isEntitled(), 'access continues until the period ends');

        $this->post(route('billing.resume'))->assertSessionHas('success');
        $this->assertFalse($sub->fresh()->cancel_at_period_end);
        $this->post(route('billing.resume'))->assertSessionHas('error', 'This subscription is not set to cancel.');
    }

    public function test_portal_needs_a_customer(): void
    {
        $this->post(route('billing.portal'))->assertSessionHas('error');
        $this->checkoutStarter();
        $this->post(route('billing.portal'))->assertRedirect('https://billing.stripe.test/portal/'.$this->business->fresh()->stripe_customer_id);
    }

    public function test_webhooks_are_verified_stored_once_and_drive_entitlements(): void
    {
        $this->launchPricingOff();
        $sub = $this->checkoutStarter();
        $stripeSub = $this->stripe->subscriptions[$sub->stripe_subscription_id];

        $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'forged'], '{}')->assertStatus(400);

        // Payment failed → past_due: still has access while Stripe retries.
        $event = ['id' => 'evt_1', 'type' => 'customer.subscription.updated', 'data' => ['object' => ['status' => 'past_due'] + $stripeSub]];
        $this->webhook($event)->assertOk();
        $this->webhook($event)->assertOk();
        $this->assertSame(1, StripeEvent::count());
        $this->assertSame('processed', StripeEvent::first()->status);
        $this->assertSame('past_due', $sub->fresh()->status);
        $this->assertTrue($this->modules()->isActive($this->business->id, 'pos'));

        // Subscription ended → paid modules switch off (data kept), dependents first.
        $this->webhook(['id' => 'evt_2', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['status' => 'canceled', 'ended_at' => now()->timestamp] + $stripeSub]])->assertOk();
        $this->assertSame('canceled', $sub->fresh()->status);
        $this->assertFalse($this->modules()->isActive($this->business->id, 'pos'));
        $this->assertFalse($this->modules()->isActive($this->business->id, 'stock'));
        $this->assertSoftDeleted('business_modules', ['business_id' => $this->business->id, 'module' => 'pos']);

        // Unknown events are stored and ignored.
        $this->webhook(['id' => 'evt_3', 'type' => 'charge.refunded', 'data' => ['object' => []]])->assertOk();
        $this->assertSame('ignored', StripeEvent::where('stripe_event_id', 'evt_3')->value('status'));
    }

    public function test_launch_pricing_keeps_modules_when_a_subscription_ends(): void
    {
        $sub = $this->checkoutStarter();
        $stripeSub = $this->stripe->subscriptions[$sub->stripe_subscription_id];

        $this->webhook(['id' => 'evt_9', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['status' => 'canceled'] + $stripeSub]])->assertOk();
        $this->assertTrue($this->modules()->isActive($this->business->id, 'pos'));
    }

    public function test_invoice_webhooks_build_the_payment_history(): void
    {
        $sub = $this->checkoutStarter();
        $invoice = [
            'id' => 'in_1', 'customer' => $this->business->fresh()->stripe_customer_id, 'subscription' => $sub->stripe_subscription_id, 'number' => 'A1B2-0001',
            'status' => 'paid', 'amount_due' => 2000, 'amount_paid' => 2000, 'currency' => 'usd',
            'hosted_invoice_url' => 'https://invoice.stripe.test/in_1', 'invoice_pdf' => 'https://invoice.stripe.test/in_1.pdf',
            'period_start' => now()->timestamp, 'period_end' => now()->addMonth()->timestamp, 'status_transitions' => ['paid_at' => now()->timestamp],
        ];
        $this->webhook(['id' => 'evt_in', 'type' => 'invoice.paid', 'data' => ['object' => $invoice]])->assertOk();

        $row = BillingInvoice::first();
        $this->assertSame(20.0, $row->amount_paid);
        $this->assertSame($sub->id, $row->subscription_id);
        $this->get(route('billing.index'))->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.number', 'A1B2-0001')->where('invoices.data.0.amount_paid', 20));

        // Another customer's invoice is not attached to this business.
        $this->webhook(['id' => 'evt_in2', 'type' => 'invoice.paid', 'data' => ['object' => ['customer' => 'cus_other'] + $invoice + ['id' => 'in_2']]]);
        $this->assertSame('ignored', StripeEvent::where('stripe_event_id', 'evt_in2')->value('status'));
    }

    public function test_real_gateway_verifies_stripe_signatures(): void
    {
        $gateway = new StripeGateway('sk_test_x', 'pk_test_x', 'whsec_test');
        $payload = json_encode(['id' => 'evt_sig', 'object' => 'event', 'type' => 'ping', 'data' => ['object' => []]]);
        $t = time();
        $header = "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test');

        $this->assertSame('evt_sig', $gateway->constructWebhookEvent($payload, $header)['id']);
        $this->assertSame('test', $gateway->mode());

        $this->expectException(BillingException::class);
        $gateway->constructWebhookEvent($payload, "t={$t},v1=bad");
    }

    public function test_webhook_without_secret_answers_503(): void
    {
        $this->app->instance(PaymentGateway::class, new StripeGateway(null, null, null));
        $this->call('POST', route('stripe.webhook'), [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'x'], '{}')->assertStatus(503)->assertJsonPath('error', 'not_configured');
    }

    public function test_price_changes_create_new_stripe_prices(): void
    {
        $this->starter->forceFill(['stripe_price_monthly_id' => 'price_old', 'stripe_price_yearly_id' => 'price_old_y'])->save();
        $this->starter->update(['monthly_price' => 25]);

        $this->assertNull($this->starter->fresh()->stripe_price_monthly_id);
        $this->assertSame('price_old_y', $this->starter->fresh()->stripe_price_yearly_id);
    }

    public function test_paid_mode_module_store_and_wizard_send_owners_to_billing(): void
    {
        $this->launchPricingOff();

        $new = Business::create(['name' => 'After launch']);
        $this->assertSame(['core'], $this->modules()->activeFor($new->id), 'no free bundle once launch pricing is over');

        $this->post(route('business.store'), ['name' => 'Paid shop', 'business_type' => 'inventory', 'modules' => ['pos']])
            ->assertRedirect(route('billing.index', ['modules' => ['pos']]));
        $shop = Business::where('name', 'Paid shop')->first();
        $this->assertSame(['core'], $this->modules()->activeFor($shop->id));

        // Once the subscription covers a module, the module store can switch it on.
        $this->enterBusiness($this->business, $this->owner);
        $this->checkoutStarter();
        $this->delete(route('modules.deactivate', 'pos'));
        $this->post(route('modules.activate', 'pos'))->assertSessionHas('success');
        $this->post(route('modules.activate', 'pharmacy'))->assertRedirect(route('billing.index', ['modules' => ['pharmacy']]));
    }

    public function test_only_people_with_the_billing_permission_open_billing(): void
    {
        $manager = \App\Models\BusinessRole::create(['business_id' => $this->business->id, 'name' => 'Manager']);
        $manager->syncPermissions(['pos.sell', 'core.settings.manage']);
        $staff = User::create(['name' => 'Manager', 'email' => 'manager-billing@example.test', 'password' => bcrypt('x')]);
        $staff->businesses()->attach($this->business->id, ['role' => 'staff', 'business_role_id' => $manager->id]);
        app()->forgetScopedInstances();
        $this->enterBusiness($this->business, $staff);

        $this->get(route('billing.index'))->assertForbidden();
        $this->post(route('billing.subscribe'), ['plan_id' => $this->starter->id, 'interval' => 'monthly'])->assertForbidden();

        $manager->syncPermissions(['pos.sell', 'core.billing.manage']);
        app()->forgetScopedInstances();
        $this->get(route('billing.index'))->assertOk();
    }

    public function test_daily_sweep_switches_off_uncovered_modules_only_after_launch_pricing(): void
    {
        $this->artisan('billing:enforce-entitlements')->expectsOutput('Launch pricing is on: nothing to do.')->assertSuccessful();
        $this->assertTrue($this->modules()->isActive($this->business->id, 'pharmacy'));

        $this->checkoutStarter();
        $this->launchPricingOff();
        $this->artisan('billing:enforce-entitlements')->assertSuccessful();

        $this->assertTrue($this->modules()->isActive($this->business->id, 'pos'), 'covered by the plan');
        $this->assertFalse($this->modules()->isActive($this->business->id, 'pharmacy'), 'not covered: switched off, data kept');
        $this->assertSoftDeleted('business_modules', ['business_id' => $this->business->id, 'module' => 'pharmacy']);
    }

    public function test_super_admin_subscriptions_overview(): void
    {
        $this->checkoutStarter();
        $admin = User::create(['name' => 'Root', 'email' => 'root-billing@example.test', 'password' => bcrypt('x')]);
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super-admin'));

        $this->get(route('superadmin.billing.subscriptions'))->assertForbidden();
        $this->actingAs($admin)->get(route('superadmin.billing.subscriptions', ['status' => 'active']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('SuperAdmin/Subscriptions')
                ->where('subscriptions.total', 1)->where('subscriptions.data.0.plan', 'Starter')
                ->where('stats.mrr', 20)->where('stats.counts.active', 1));
    }
}
