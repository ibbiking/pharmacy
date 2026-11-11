<?php

namespace App\Listeners;

use App\Events\StockReachedAlertLevel;
use App\Models\Business;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use App\Services\Access\PermissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Roadmap 13c: tell only THAT business's people — active members (owners included) allowed to see stock reports.
 * (The old listener, never fired, would have notified every user on the platform.) Registered in AppServiceProvider;
 * event discovery stays off.
 */
class NotifyStockAlert implements ShouldQueue
{
    public function handle(StockReachedAlertLevel $event): void
    {
        $business = Business::find($event->businessId);
        if (! $business) {
            return;
        }
        $permissions = app(PermissionService::class);
        $recipients = User::query()
            ->whereIn('users.id', \DB::table('business_user')->where('business_id', $business->id)->where('status', 'active')->select('user_id'))
            ->get()
            ->filter(fn (User $u) => $permissions->allows($u, $business->id, 'stock.reports.view'));

        if ($recipients->isNotEmpty()) {
            $notification = new StockAlertNotification($business->id, $business->name, $event->invoiceNo, $event->alerts);
            Notification::send($recipients, $notification); // in-app bell
            // Email through this business's own SMTP account (docs/MODULES_AND_PLANS.md → Per-business email).
            foreach ($recipients as $user) {
                \App\Jobs\SendBusinessMail::dispatch($business, $user->email, new \App\Mail\BusinessMessage(
                    $business, $notification->subject(), "{$business->name}: stock is running low", $notification->emailLines(), [],
                    ['Open stock alerts', route('reports.stock_indication')], 'You get this because you can see stock reports in this business.'));
            }
        }
    }
}
