<?php

namespace App\Jobs;

use App\Models\Billing\StripeEvent;
use App\Services\Billing\BillingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Applies one stored Stripe webhook event. Retried with backoff; a resent event is applied once. */
class ProcessStripeEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $eventId)
    {
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(BillingService $billing): void
    {
        $event = StripeEvent::find($this->eventId);
        if (! $event || in_array($event->status, ['processed', 'ignored'], true)) {
            return;
        }

        try {
            $result = $billing->handleEvent($event);
            $event->update(['status' => $result, 'processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            throw $e;
        }
    }
}
