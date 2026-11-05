<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeEvent;
use App\Models\Billing\StripeEvent;
use App\Services\Billing\Exceptions\BillingException;
use App\Services\Billing\Gateway\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /stripe/webhook (no session, no CSRF). Verifies the signature, stores the event once and
 * queues it. Answers fast (Stripe retries anything that isn't 2xx).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway)
    {
        try {
            $event = $gateway->constructWebhookEvent($request->getContent(), (string) $request->header('Stripe-Signature', ''));
        } catch (BillingException $e) {
            Log::warning('Stripe webhook rejected', ['reason' => $e->reason, 'ip' => $request->ip()]);

            return response()->json(['error' => $e->reason], $e->status());
        }

        if (empty($event['id']) || empty($event['type'])) {
            return response()->json(['error' => 'invalid_event'], 400);
        }

        $stored = StripeEvent::firstOrCreate(['stripe_event_id' => $event['id']], [
            'type' => $event['type'],
            'payload' => $event,
        ]);

        if ($stored->wasRecentlyCreated || $stored->status === 'failed') {
            ProcessStripeEvent::dispatch($stored->id);
        }

        return response()->json(['received' => true]);
    }
}
