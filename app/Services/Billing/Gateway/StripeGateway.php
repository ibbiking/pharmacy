<?php

namespace App\Services\Billing\Gateway;

use App\Services\Billing\Exceptions\BillingException;
use App\Services\Billing\Exceptions\BillingNotConfiguredException;
use Closure;
use Illuminate\Support\Facades\Log;
use Stripe\Exception as StripeError;
use Stripe\StripeClient;
use Stripe\Webhook;

/** Stripe implementation of PaymentGateway (stripe/stripe-php). Keys come from config/services.php. */
class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secret,
        private readonly ?string $publishable,
        private readonly ?string $webhookSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return filled($this->secret) && filled($this->publishable);
    }

    public function mode(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return str_starts_with((string) $this->secret, 'sk_live_') || str_starts_with((string) $this->secret, 'rk_live_') ? 'live' : 'test';
    }

    public function webhookConfigured(): bool
    {
        return filled($this->webhookSecret);
    }

    public function createCustomer(array $params): string
    {
        return $this->call('create customer', fn (StripeClient $s) => $s->customers->create($params)->id);
    }

    public function createProduct(string $name, array $metadata): string
    {
        return $this->call('create product', fn (StripeClient $s) => $s->products->create(['name' => $name, 'metadata' => $metadata])->id);
    }

    public function createPrice(string $productId, int $unitAmount, string $currency, string $interval, array $metadata = []): string
    {
        return $this->call('create price', fn (StripeClient $s) => $s->prices->create([
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => strtolower($currency),
            'recurring' => ['interval' => $interval === 'yearly' ? 'year' : 'month'],
            'metadata' => $metadata,
        ])->id);
    }

    public function createCheckoutSession(array $params): array
    {
        return $this->call('start checkout', function (StripeClient $s) use ($params) {
            $session = $s->checkout->sessions->create($params);

            return ['id' => $session->id, 'url' => $session->url];
        });
    }

    public function retrieveCheckoutSession(string $id): array
    {
        return $this->call('read checkout', fn (StripeClient $s) => $s->checkout->sessions->retrieve($id)->toArray());
    }

    public function createPortalSession(string $customerId, string $returnUrl): string
    {
        return $this->call('open billing portal', fn (StripeClient $s) => $s->billingPortal->sessions->create(['customer' => $customerId, 'return_url' => $returnUrl])->url);
    }

    public function retrieveSubscription(string $id): array
    {
        return $this->call('read subscription', fn (StripeClient $s) => $s->subscriptions->retrieve($id)->toArray());
    }

    public function updateSubscription(string $id, array $params): array
    {
        return $this->call('update subscription', fn (StripeClient $s) => $s->subscriptions->update($id, $params)->toArray());
    }

    public function cancelSubscriptionNow(string $id): array
    {
        return $this->call('cancel subscription', fn (StripeClient $s) => $s->subscriptions->cancel($id)->toArray());
    }

    public function constructWebhookEvent(string $payload, string $signature): array
    {
        if (! $this->webhookConfigured()) {
            throw new BillingNotConfiguredException('Stripe webhooks');
        }
        try {
            return Webhook::constructEvent($payload, $signature, (string) $this->webhookSecret)->toArray();
        } catch (StripeError\SignatureVerificationException|\UnexpectedValueException $e) {
            throw new BillingException('Invalid Stripe signature.', 'webhook_signature', $e);
        }
    }

    /** Run a Stripe call and translate every Stripe error into a BillingException with a friendly message. */
    private function call(string $action, Closure $fn): mixed
    {
        if (! $this->isConfigured()) {
            throw new BillingNotConfiguredException();
        }
        $this->client ??= new StripeClient(['api_key' => (string) $this->secret, 'max_network_retries' => 2]);

        try {
            return $fn($this->client);
        } catch (StripeError\CardException $e) {
            throw $this->fail($action, $e, $e->getMessage() ?: 'The card was declined.', 'card');
        } catch (StripeError\RateLimitException $e) {
            throw $this->fail($action, $e, 'Stripe is busy right now. Please try again in a minute.', 'rate_limit');
        } catch (StripeError\AuthenticationException|StripeError\PermissionException $e) {
            throw $this->fail($action, $e, 'Online payments are misconfigured (the Stripe keys were rejected). The platform owner has been notified in the log.', 'invalid_keys');
        } catch (StripeError\ApiConnectionException $e) {
            throw $this->fail($action, $e, 'Could not reach Stripe. Check the internet connection and try again.', 'network');
        } catch (StripeError\InvalidRequestException $e) {
            throw $this->fail($action, $e, 'Stripe could not '.$action.'. Nothing was charged. Please try again or contact support.', 'invalid_request');
        } catch (StripeError\ApiErrorException $e) {
            throw $this->fail($action, $e, 'Stripe had a problem ('.$action.'). Nothing was charged. Please try again.', 'provider');
        }
    }

    private function fail(string $action, StripeError\ApiErrorException $e, string $message, string $reason): BillingException
    {
        // Stripe exception messages never contain the secret key; the request id helps support find it in Stripe.
        Log::warning('Stripe call failed', [
            'action' => $action,
            'reason' => $reason,
            'stripe_code' => $e->getStripeCode(),
            'http_status' => $e->getHttpStatus(),
            'request_id' => $e->getRequestId(),
            'message' => $e->getMessage(),
        ]);

        return new BillingException($message, $reason, $e);
    }
}
