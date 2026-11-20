<?php

namespace App\Providers;

use App\Events\ModuleActivated;
use App\Services\Business\BusinessPreferenceDefaults;
use App\Services\Modules\ModuleManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request/command: caches which modules each business has on.
        $this->app->scoped(ModuleManager::class);
        $this->app->scoped(\App\Services\Access\PermissionService::class);
        $this->app->scoped(\App\Services\Stock\WarehouseService::class);
        $this->app->scoped(\App\Services\Accounting\AccountingService::class);

        // Stripe (Roadmap 4e). Empty keys are fine: billing then shows "not connected yet".
        $this->app->singleton(\App\Services\Billing\Gateway\PaymentGateway::class, fn () => new \App\Services\Billing\Gateway\StripeGateway(
            config('services.stripe.secret'),
            config('services.stripe.key'),
            config('services.stripe.webhook_secret'),
        ));
    }

    public function boot(): void
    {
        // Every sign-in is logged for the super admin's Visits page (who, which browser, demo or not).
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function (\Illuminate\Auth\Events\Login $event) {
            try {
                $request = request();
                if (! \Illuminate\Support\Facades\Schema::hasTable('login_events') || app()->runningInConsole() && ! app()->runningUnitTests()) {
                    return;
                }
                \Illuminate\Support\Facades\DB::table('login_events')->insert([
                    'user_id' => $event->user->getAuthIdentifier(), 'email' => mb_substr((string) ($event->user->email ?? ''), 0, 191),
                    'visitor_id' => \App\Support\Visitor::id($request), 'is_demo' => method_exists($event->user, 'isDemoAccount') && $event->user->isDemoAccount(),
                    'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'created_at' => now(),
                ] + \App\Support\Visitor::agent($request->userAgent()));
            } catch (\Throwable $e) {
                report($e);
            }
        });

        // Per-business module permissions ('pos.sell', …) answer through the Gate; anything else
        // (legacy Spatie permissions such as 'view-category') falls through to Spatie unchanged.
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            $permissions = app(\App\Services\Access\PermissionService::class);
            if (! str_contains($ability, '.') || ! $permissions->exists($ability)) {
                return null;
            }
            $businessId = (int) (session('impersonate_business_id') ?? session('business_id')) ?: null;

            return $permissions->allows($user, $businessId, $ability);
        });

        // Event discovery is off (bootstrap/app.php): listeners are registered explicitly here.
        Event::listen(\App\Events\StockReachedAlertLevel::class, \App\Listeners\NotifyStockAlert::class);
        // Roadmap 10: the books follow every module (does nothing while Accounting is off).
        foreach ([\App\Events\SaleCompleted::class, \App\Events\SaleReturned::class, \App\Events\GoodsReceived::class, \App\Events\PurchaseChanged::class,
            \App\Events\StockAdjusted::class, \App\Events\DocumentChanged::class, \App\Events\PaymentChanged::class, \App\Events\ExpenseChanged::class] as $event) {
            Event::listen($event, \App\Listeners\PostToLedger::class);
        }
        foreach (['customer' => \App\Models\Customer::class, 'supplier' => \App\Models\Supplier::class] as $kind => $model) {
            $model::saved(fn ($party) => rescue(fn () => app(\App\Services\Accounting\LedgerPoster::class)->opening($kind, $party)));
            $model::deleted(fn ($party) => rescue(fn () => app(\App\Services\Accounting\LedgerPoster::class)->opening($kind, $party)));
        }
        Event::listen(ModuleActivated::class, function (ModuleActivated $event) {
            if (in_array($event->module, ['stock', 'pos'], true)) {
                app(BusinessPreferenceDefaults::class)->ensure($event->business->id);
            }
            if ($event->module === 'inventory_pro') {
                app(\App\Services\Stock\WarehouseService::class)->ensureDefault($event->business->id);
            }
            if ($event->module === 'accounting') {
                app(\App\Services\Accounting\AccountingService::class)->ensureChart($event->business->id);
                // Past data is converted in the background; new postings wait and follow in order (BooksSyncService).
                app(\App\Services\Accounting\BooksSyncService::class)->start($event->business->id, 'switched_on');
            }
            if ($event->module === 'expense') {
                app(\App\Services\Expense\ExpenseService::class)->ensureDefaults($event->business->id);
            }
        });
    }
}
