<?php

namespace Tests\Support;

use App\Services\Billing\Exceptions\BillingException;
use App\Services\Billing\Gateway\PaymentGateway;

/** In-memory Stripe stand-in: records calls, returns realistic shapes, can be told to fail. */
class FakePaymentGateway implements PaymentGateway
{
    public array $calls = [];
    public array $sessions = [];
    public array $subscriptions = [];
    public ?BillingException $failNext = null;
    private int $seq = 0;

    public function __construct(public bool $configured = true)
    {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function mode(): ?string
    {
        return $this->configured ? 'test' : null;
    }

    public function webhookConfigured(): bool
    {
        return $this->configured;
    }

    private function record(string $method, array $args): void
    {
        $this->calls[] = [$method, $args];
        if ($this->failNext) {
            $e = $this->failNext;
            $this->failNext = null;
            throw $e;
        }
    }

    public function called(string $method): array
    {
        return array_values(array_map(fn ($c) => $c[1], array_filter($this->calls, fn ($c) => $c[0] === $method)));
    }

    public function createCustomer(array $params): string
    {
        $this->record(__FUNCTION__, [$params]);

        return 'cus_'.(++$this->seq);
    }

    public function createProduct(string $name, array $metadata): string
    {
        $this->record(__FUNCTION__, [$name, $metadata]);

        return 'prod_'.(++$this->seq);
    }

    public function createPrice(string $productId, int $unitAmount, string $currency, string $interval, array $metadata = []): string
    {
        $this->record(__FUNCTION__, [$productId, $unitAmount, $currency, $interval]);

        return 'price_'.(++$this->seq);
    }

    public function createCheckoutSession(array $params): array
    {
        $this->record(__FUNCTION__, [$params]);
        $id = 'cs_test_'.(++$this->seq);
        $this->sessions[$id] = $params + ['id' => $id, 'status' => 'open', 'subscription' => null];

        return ['id' => $id, 'url' => "https://checkout.stripe.test/{$id}"];
    }

    /** Simulate the customer paying: the session completes and a Stripe subscription exists. */
    public function completeCheckout(string $sessionId, string $status = 'active', ?int $trialEnd = null): array
    {
        $session = $this->sessions[$sessionId];
        $subId = 'sub_'.(++$this->seq);
        $this->subscriptions[$subId] = [
            'id' => $subId,
            'status' => $status,
            'customer' => $session['customer'],
            'cancel_at_period_end' => false,
            'trial_end' => $trialEnd,
            'ended_at' => null,
            'metadata' => $session['subscription_data']['metadata'] ?? [],
            'items' => ['data' => array_map(fn ($li) => [
                'id' => 'si_'.(++$this->seq),
                'price' => ['id' => $li['price']],
                'current_period_end' => now()->addMonth()->timestamp,
            ], $session['line_items'])],
        ];
        $this->sessions[$sessionId]['status'] = 'complete';
        $this->sessions[$sessionId]['subscription'] = $subId;

        return $this->subscriptions[$subId];
    }

    public function retrieveCheckoutSession(string $id): array
    {
        $this->record(__FUNCTION__, [$id]);

        return $this->sessions[$id] ?? throw new BillingException('Stripe could not read checkout.', 'invalid_request');
    }

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        $this->record(__FUNCTION__, [$customerId, $returnUrl]);

        return 'https://billing.stripe.test/portal/'.$customerId;
    }

    public function retrieveSubscription(string $id): array
    {
        $this->record(__FUNCTION__, [$id]);

        return $this->subscriptions[$id];
    }

    public function updateSubscription(string $id, array $params): array
    {
        $this->record(__FUNCTION__, [$id, $params]);
        $sub = &$this->subscriptions[$id];
        if (array_key_exists('cancel_at_period_end', $params)) {
            $sub['cancel_at_period_end'] = $params['cancel_at_period_end'];
        }
        if (isset($params['items'])) {
            $sub['items']['data'] = array_values(array_map(fn ($i) => [
                'id' => 'si_'.(++$this->seq), 'price' => ['id' => $i['price']], 'current_period_end' => now()->addMonth()->timestamp,
            ], array_filter($params['items'], fn ($i) => empty($i['deleted']))));
        }

        return $sub;
    }

    public function cancelSubscriptionNow(string $id): array
    {
        $this->record(__FUNCTION__, [$id]);
        $this->subscriptions[$id]['status'] = 'canceled';

        return $this->subscriptions[$id];
    }

    public function constructWebhookEvent(string $payload, string $signature): array
    {
        if ($signature !== 'valid') {
            throw new BillingException('Invalid Stripe signature.', 'webhook_signature');
        }

        return json_decode($payload, true);
    }
}
