<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\Mail\BusinessMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a business email in the background through the business's own mail account, so a cashier
 * or owner never waits on SMTP. Retries 3 times with backoff; failures land in failed_jobs.
 */
class SendBusinessMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public Business $business, public string|array $to, public Mailable $mailable)
    {
    }

    public function handle(BusinessMailer $mailer): void
    {
        $mailer->send($this->business, $this->to, $this->mailable);
    }
}
