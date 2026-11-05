<?php

namespace App\Services\Billing;

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
use App\Services\Billing\Exceptions\BillingNotConfiguredException;
use App\Services\Billing\Gateway\PaymentGateway;
use App\Services\Modules\ModuleManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Subscriptions for businesses (Roadmap 4e). One flow for plans and custom module sets:
 *   quote → Stripe Checkout (or instant activation when the total is 0) → webhook / return-page
 *   confirmation → modules switched on. Changes are prorated, cancellation is at period end,
 *   past_due keeps access while Stripe retries the card, ended subscriptions switch paid modules off
 *   (unless launch pricing "Free for everyone" is on).
 */
class BillingService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ModuleManager $modules,
        private readonly PricingService $pricing,
    ) {
    }

    /** What the screens need to know about the payment provider. */
    public function status(): array
    {
        return [
            'configured' => $this->gateway->isConfigured(),
            'mode' => $this->gateway->mode(),
            'webhooks' => $this->gateway->webhookConfigured(),
            'free' => PlatformSetting::freeForEveryone(),
        ];
    }

    /**
     * Price a plan (+ extra modules) or a custom set of modules. Dependencies are added automatically.
     *
     * @return array{plan: ?array, modules: string[], lines: array, total: float, currency: string, interval: string, trial_days: int}
     */
    public function quote(?Plan $plan, array $extraModules, string $interval): array
    {
        $interval = $interval === 'yearly' ? 'yearly' : 'monthly';
        $column = $interval === 'yearly' ? 'yearly_price' : 'monthly_price';
        $covered = [];
        $lines = [];

        if ($plan) {
            foreach ($plan->modules as $m) {
                foreach ($this->modules->withDependencies($m) as $key) {
                    $covered[$key] = true;
                }
            }
            $lines[] = ['type' => 'plan', 'key' => $plan->slug, 'name' => $plan->name.' plan', 'price' => (float) $plan->{$column}, 'currency' => $plan->currency];
        }

        $extras = [];
        foreach ($extraModules as $m) {
            if (! $this->modules->exists($m)) {
                continue;
            }
            foreach ($this->modules->withDependencies($m) as $key) {
                if ($key !== 'core' && ! isset($covered[$key])) {
                    $extras[$key] = true;
                }
            }
        }
        $prices = ModulePrice::query()->whereIn('module', array_keys($extras))->get()->keyBy('module');
        foreach (array_keys($extras) as $key) {
            $lines[] = [
                'type' => 'module', 'key' => $key, 'name' => $this->modules->definition($key)['name'],
                'price' => (float) ($prices[$key]->{$column} ?? 0), 'currency' => $prices[$key]->currency ?? ($plan->currency ?? 'USD'),
                'added_for_dependency' => ! in_array($key, $extraModules, true),
            ];
        }

        $currencies = array_values(array_unique(array_column($lines, 'currency')));
        if (count($currencies) > 1) {
            throw new BillingException('These prices are in different currencies ('.implode(', ', $currencies).'), so they cannot be on one subscription. Please contact support.', 'currency');
        }

        return [
            'plan' => $plan?->only(['id', 'name', 'slug']),
            'modules' => array_values(array_unique(array_merge(array_keys($covered), array_keys($extras)))),
            'lines' => $lines,
            'total' => round(array_sum(array_column($lines, 'price')), 2),
            'currency' => $currencies[0] ?? 'USD',
            'interval' => $interval,
            'trial_days' => (int) ($plan->trial_days ?? 0),
        ];
    }

    /**
     * Start a subscription. Returns ['url' => Stripe Checkout URL] or ['activated' => true] when nothing
     * has to be paid (total 0).
     */
    public function subscribe(Business $business, User $user, ?Plan $plan, array $extraModules, string $interval): array
    {
        $current = Subscription::currentFor($business->id);
        if ($current && $current->isEntitled()) {
            throw new BillingException('This business already has an active subscription. Use "Change plan" instead.', 'state');
        }
        $quote = $this->quote($plan, $extraModules, $interval);
        if (! $quote['modules']) {
            throw new BillingException('Choose a plan or at least one module.', 'state');
        }

        // Nothing to pay: switch the modules on straight away (no Stripe needed).
        if ($quote['total'] <= 0) {
            $sub = $this->newLocalSubscription($business, $user, $plan, $quote, 'active');
            $this->applyEntitlements($business);

            return ['activated' => true, 'subscription' => $sub];
        }

        if (! $this->gateway->isConfigured()) {
            throw new BillingNotConfiguredException();
        }

        // An abandoned checkout of this business is replaced, not left lying around.
        Subscription::query()->where('business_id', $business->id)->where('status', 'incomplete')->whereNull('stripe_subscription_id')->get()->each->delete();

        $items = $this->stripeItems($plan, $quote);
        $sub = $this->newLocalSubscription($business, $user, $plan, $quote, 'incomplete', $items);

        $trial = $quote['trial_days'] > 0 && ! $this->hadTrial($business) ? $quote['trial_days'] : null;
        $base = route('billing.index');
        $metadata = ['business_id' => (string) $business->id, 'subscription_id' => (string) $sub->id];

        try {
            $session = $this->gateway->createCheckoutSession(array_filter([
                'mode' => 'subscription',
                'customer' => $this->ensureCustomer($business, $user),
                'client_reference_id' => (string) $business->id,
                'line_items' => array_map(fn ($i) => ['price' => $i['price_id'], 'quantity' => 1], $items),
                'allow_promotion_codes' => true,
                'success_url' => $base.'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $base.'?checkout=cancelled',
                'metadata' => $metadata,
                'subscription_data' => array_filter(['metadata' => $metadata, 'trial_period_days' => $trial]),
            ]));
        } catch (BillingException $e) {
            $sub->delete();
            throw $e;
        }

        $sub->update(['stripe_checkout_session_id' => $session['id']]);

        return ['url' => $session['url'], 'subscription' => $sub];
    }

    /** Return page after Checkout: confirm with Stripe directly, so it works even before the webhook arrives. */
    public function confirmCheckout(Business $business, string $sessionId): ?Subscription
    {
        $session = $this->gateway->retrieveCheckoutSession($sessionId);
        if ((string) ($session['client_reference_id'] ?? '') !== (string) $business->id) {
            throw new BillingException('This payment belongs to another business.', 'state');
        }
        if (($session['status'] ?? null) !== 'complete' || empty($session['subscription'])) {
            return null; // still open or expired: nothing to do
        }
        $subscriptionId = is_array($session['subscription']) ? $session['subscription']['id'] : $session['subscription'];

        return $this->syncSubscription($this->gateway->retrieveSubscription($subscriptionId), (int) ($session['metadata']['subscription_id'] ?? 0));
    }

    /** Change plan / modules / interval of the running subscription (prorated). */
    public function change(Business $business, ?Plan $plan, array $extraModules, string $interval): Subscription
    {
        $sub = Subscription::currentFor($business->id);
        if (! $sub || ! $sub->isEntitled()) {
            throw new BillingException('There is no active subscription to change. Choose a plan first.', 'state');
        }
        $quote = $this->quote($plan, $extraModules, $interval);
        if (! $quote['modules']) {
            throw new BillingException('Choose a plan or at least one module.', 'state');
        }

        // A free (local) subscription moving to a paid one goes through Checkout instead.
        if (! $sub->stripe_subscription_id) {
            if ($quote['total'] > 0) {
                throw new BillingException('Your current plan is free. Cancel it first, then subscribe to the paid plan through checkout.', 'state');
            }
            $sub->update(['plan_id' => $plan?->id, 'modules' => $quote['modules'], 'interval' => $quote['interval'], 'amount' => 0, 'currency' => $quote['currency']]);
            $this->applyEntitlements($business);

            return $sub->fresh();
        }
        if ($quote['total'] <= 0) {
            throw new BillingException('To move to a free plan, cancel the subscription; it stays active until the end of the paid period.', 'state');
        }

        $items = $this->stripeItems($plan, $quote);
        $remove = array_map(fn ($i) => ['id' => $i['item_id'], 'deleted' => true], array_filter($sub->items ?? [], fn ($i) => ! empty($i['item_id'])));
        $stripe = $this->gateway->updateSubscription($sub->stripe_subscription_id, [
            'items' => array_merge($remove, array_map(fn ($i) => ['price' => $i['price_id'], 'quantity' => 1], $items)),
            'proration_behavior' => 'create_prorations',
            'cancel_at_period_end' => false,
            'metadata' => ['business_id' => (string) $business->id, 'subscription_id' => (string) $sub->id],
        ]);

        $sub->update(['plan_id' => $plan?->id, 'modules' => $quote['modules'], 'items' => $items, 'interval' => $quote['interval'], 'amount' => $quote['total'], 'currency' => $quote['currency']]);

        return $this->syncSubscription($stripe, $sub->id);
    }

    /** Cancel at the end of the paid period (access continues until then). */
    public function cancel(Business $business): Subscription
    {
        $sub = $this->requireCurrent($business);
        if (! $sub->stripe_subscription_id) {
            $sub->update(['status' => 'canceled', 'ends_at' => now()]);
            $this->applyEntitlements($business);

            return $sub;
        }

        return $this->syncSubscription($this->gateway->updateSubscription($sub->stripe_subscription_id, ['cancel_at_period_end' => true]), $sub->id);
    }

    public function resume(Business $business): Subscription
    {
        $sub = $this->requireCurrent($business);
        if (! $sub->cancel_at_period_end || ! $sub->stripe_subscription_id) {
            throw new BillingException('This subscription is not set to cancel.', 'state');
        }

        return $this->syncSubscription($this->gateway->updateSubscription($sub->stripe_subscription_id, ['cancel_at_period_end' => false]), $sub->id);
    }

    /** Stripe's customer portal: payment methods, invoices, billing details. */
    public function portalUrl(Business $business): string
    {
        if (! $business->stripe_customer_id) {
            throw new BillingException('There is no payment account for this business yet. Subscribe to a plan first.', 'state');
        }

        return $this->gateway->createPortalSession($business->stripe_customer_id, route('billing.index'));
    }

    /** Process one stored webhook event (called by the ProcessStripeEvent job). */
    public function handleEvent(StripeEvent $event): string
    {
        $object = $event->payload['data']['object'] ?? [];

        switch ($event->type) {
            case 'checkout.session.completed':
                if (($object['mode'] ?? null) !== 'subscription' || empty($object['subscription'])) {
                    return 'ignored';
                }
                $this->syncSubscription($this->gateway->retrieveSubscription(is_array($object['subscription']) ? $object['subscription']['id'] : $object['subscription']), (int) ($object['metadata']['subscription_id'] ?? 0));

                return 'processed';

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
            case 'customer.subscription.paused':
            case 'customer.subscription.resumed':
                return $this->syncSubscription($object, (int) ($object['metadata']['subscription_id'] ?? 0)) ? 'processed' : 'ignored';

            case 'invoice.created':
            case 'invoice.finalized':
            case 'invoice.paid':
            case 'invoice.payment_failed':
            case 'invoice.payment_succeeded':
            case 'invoice.updated':
            case 'invoice.voided':
            case 'invoice.marked_uncollectible':
                return $this->recordInvoice($object) ? 'processed' : 'ignored';

            default:
                return 'ignored';
        }
    }

    /** Copy Stripe's view of a subscription onto our row, then switch modules on/off accordingly. */
    public function syncSubscription(array $stripe, int $localId = 0): ?Subscription
    {
        $sub = Subscription::withTrashed()->where('stripe_subscription_id', $stripe['id'] ?? '')->first()
            ?? ($localId ? Subscription::withTrashed()->find($localId) : null);
        if (! $sub) {
            Log::info('Stripe subscription not linked to any business', ['stripe_subscription_id' => $stripe['id'] ?? null]);

            return null;
        }

        $ts = fn ($v) => $v ? Carbon::createFromTimestamp((int) $v) : null;
        $itemsData = $stripe['items']['data'] ?? [];
        // API 2025+: billing periods are per item; older API versions have them on the subscription.
        $periodEnd = $stripe['current_period_end'] ?? ($itemsData[0]['current_period_end'] ?? null);

        $items = $sub->items ?? [];
        foreach ($itemsData as $si) {
            foreach ($items as &$mine) {
                if (($mine['price_id'] ?? null) === ($si['price']['id'] ?? null)) {
                    $mine['item_id'] = $si['id'];
                }
            }
            unset($mine);
        }

        DB::transaction(function () use ($sub, $stripe, $ts, $periodEnd, $items) {
            if ($sub->trashed()) {
                $sub->restore();
            }
            $sub->fill([
                'stripe_subscription_id' => $stripe['id'],
                'status' => $stripe['status'] ?? $sub->status,
                'items' => $items,
                'trial_ends_at' => $ts($stripe['trial_end'] ?? null),
                'current_period_end' => $ts($periodEnd),
                'cancel_at_period_end' => (bool) ($stripe['cancel_at_period_end'] ?? false),
                'ends_at' => $ts($stripe['ended_at'] ?? null) ?? ($stripe['cancel_at_period_end'] ?? false ? $ts($periodEnd) : null),
            ])->save();

            // A business has one live subscription: older unfinished checkouts are cleared.
            if ($sub->isEntitled()) {
                Subscription::query()->where('business_id', $sub->business_id)->where('id', '!=', $sub->id)
                    ->where('status', 'incomplete')->whereNull('stripe_subscription_id')->get()->each->delete();
            }
        });

        $this->applyEntitlements($sub->business);

        return $sub->fresh();
    }

    /**
     * Make the business's modules match what it pays for (docs/MODULES_AND_PLANS.md):
     *  - entitled subscription → its modules are switched on;
     *  - while launch pricing ("Free for everyone", incl. its grace date) is on, nothing is switched off;
     *  - after that, every module that no entitled subscription covers is switched off (dependents
     *    first). Data is kept and comes back when the business subscribes again. Core is never touched.
     */
    public function applyEntitlements(Business $business): void
    {
        $sub = Subscription::currentFor($business->id);
        $covered = ['core'];

        if ($sub && $sub->isEntitled()) {
            foreach ($sub->modules as $module) {
                if ($module === 'core' || ! $this->modules->exists($module) || ! ($this->modules->definition($module)['available'] ?? true)) {
                    continue;
                }
                $this->modules->activate($business, $module, 'subscription', $sub->created_by);
                BusinessModule::query()->where('business_id', $business->id)->where('module', $module)->update(['source' => 'subscription', 'expires_at' => null]);
                $covered = array_merge($covered, $this->modules->withDependencies($module));
            }
        }

        if (PlatformSetting::freeForEveryone()) {
            return;
        }

        do {
            $changed = false;
            foreach ($this->modules->activeFor($business->id) as $module) {
                if (in_array($module, $covered, true) || $this->modules->dependents($business->id, $module)) {
                    continue;
                }
                $this->modules->deactivate($business, $module);
                $changed = true;
            }
        } while ($changed);
    }

    private function recordInvoice(array $invoice): bool
    {
        $customer = is_array($invoice['customer'] ?? null) ? $invoice['customer']['id'] : ($invoice['customer'] ?? null);
        $business = $customer ? Business::query()->where('stripe_customer_id', $customer)->first() : null;
        if (! $business || empty($invoice['id'])) {
            return false;
        }
        $subscriptionId = $invoice['subscription'] ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        $sub = $subscriptionId ? Subscription::query()->where('stripe_subscription_id', is_array($subscriptionId) ? $subscriptionId['id'] : $subscriptionId)->first() : null;
        $ts = fn ($v) => $v ? Carbon::createFromTimestamp((int) $v) : null;
        $money = fn ($cents, $currency) => round(((int) $cents) / (in_array(strtolower((string) $currency), self::ZERO_DECIMAL, true) ? 1 : 100), 2);
        $currency = strtoupper((string) ($invoice['currency'] ?? 'usd'));

        BillingInvoice::withTrashed()->updateOrCreate(['stripe_invoice_id' => $invoice['id']], [
            'business_id' => $business->id,
            'subscription_id' => $sub?->id,
            'number' => $invoice['number'] ?? null,
            'status' => $invoice['status'] ?? 'open',
            'amount_due' => $money($invoice['amount_due'] ?? 0, $currency),
            'amount_paid' => $money($invoice['amount_paid'] ?? 0, $currency),
            'currency' => $currency,
            'hosted_invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $invoice['invoice_pdf'] ?? null,
            'period_start' => $ts($invoice['period_start'] ?? null),
            'period_end' => $ts($invoice['period_end'] ?? null),
            'paid_at' => ($invoice['status'] ?? null) === 'paid' ? $ts($invoice['status_transitions']['paid_at'] ?? null) ?? now() : null,
            'deleted_at' => null,
        ]);

        return true;
    }

    /** Stripe zero-decimal currencies (amounts are not in cents). */
    private const ZERO_DECIMAL = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round(in_array(strtolower($currency), self::ZERO_DECIMAL, true) ? $amount : $amount * 100);
    }

    /** Stripe price ids for the quote's paid lines (Products/Prices are created in Stripe on first use). */
    private function stripeItems(?Plan $plan, array $quote): array
    {
        $items = [];
        foreach ($quote['lines'] as $line) {
            if ($line['price'] <= 0) {
                continue; // free modules ride along without a Stripe item
            }
            $model = $line['type'] === 'plan' ? $plan : ModulePrice::query()->where('module', $line['key'])->first();
            $items[] = ['type' => $line['type'], 'key' => $line['key'], 'price_id' => $this->ensurePrice($model, $line, $quote['interval']), 'item_id' => null];
        }

        return $items;
    }

    private function ensurePrice(Plan|ModulePrice $model, array $line, string $interval): string
    {
        $column = $interval === 'yearly' ? 'stripe_price_yearly_id' : 'stripe_price_monthly_id';
        if ($model->{$column}) {
            return $model->{$column};
        }
        if (! $model->stripe_product_id) {
            $model->stripe_product_id = $this->gateway->createProduct($line['name'], ['type' => $line['type'], 'key' => $line['key']]);
        }
        $model->{$column} = $this->gateway->createPrice($model->stripe_product_id, $this->toMinorUnits($line['price'], $line['currency']), $line['currency'], $interval, ['type' => $line['type'], 'key' => $line['key']]);
        $model->saveQuietly();

        return $model->{$column};
    }

    private function ensureCustomer(Business $business, User $user): string
    {
        if ($business->stripe_customer_id) {
            return $business->stripe_customer_id;
        }
        $id = $this->gateway->createCustomer(array_filter([
            'name' => $business->name,
            'email' => $user->email,
            'phone' => $business->phone,
            'metadata' => ['business_id' => (string) $business->id],
        ]));
        $business->forceFill(['stripe_customer_id' => $id])->saveQuietly();

        return $id;
    }

    private function hadTrial(Business $business): bool
    {
        return Subscription::withTrashed()->where('business_id', $business->id)->whereNotNull('trial_ends_at')->exists();
    }

    private function newLocalSubscription(Business $business, User $user, ?Plan $plan, array $quote, string $status, array $items = []): Subscription
    {
        return Subscription::create([
            'business_id' => $business->id,
            'plan_id' => $plan?->id,
            'modules' => $quote['modules'],
            'items' => $items,
            'interval' => $quote['interval'],
            'status' => $status,
            'amount' => $quote['total'],
            'currency' => $quote['currency'],
            'created_by' => $user->id,
        ]);
    }

    private function requireCurrent(Business $business): Subscription
    {
        $sub = Subscription::currentFor($business->id);
        if (! $sub || ! $sub->isEntitled()) {
            throw new BillingException('There is no active subscription.', 'state');
        }

        return $sub;
    }
}
