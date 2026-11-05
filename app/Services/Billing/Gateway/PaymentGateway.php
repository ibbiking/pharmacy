<?php

namespace App\Services\Billing\Gateway;

/**
 * The only door to Stripe. Every method returns plain arrays (Stripe objects ->toArray()) and throws
 * App\Services\Billing\Exceptions\BillingException with a friendly message on any failure.
 * Tests bind a fake (tests/Support/FakePaymentGateway.php).
 */
interface PaymentGateway
{
    /** Both keys present. */
    public function isConfigured(): bool;

    /** 'test' | 'live' | null (not configured). */
    public function mode(): ?string;

    public function webhookConfigured(): bool;

    public function createCustomer(array $params): string;

    public function createProduct(string $name, array $metadata): string;

    /** @param int $unitAmount in the smallest currency unit (cents) */
    public function createPrice(string $productId, int $unitAmount, string $currency, string $interval, array $metadata = []): string;

    /** @return array{id: string, url: string} */
    public function createCheckoutSession(array $params): array;

    public function retrieveCheckoutSession(string $id): array;

    public function createPortalSession(string $customerId, string $returnUrl): string;

    public function retrieveSubscription(string $id): array;

    public function updateSubscription(string $id, array $params): array;

    public function cancelSubscriptionNow(string $id): array;

    /** Verify the Stripe-Signature header and return the event as an array. */
    public function constructWebhookEvent(string $payload, string $signature): array;
}
