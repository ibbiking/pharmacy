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

        // Stripe (Roadmap 4e). Empty keys are fine: billing then shows "not connected yet".
        $this->app->singleton(\App\Services\Billing\Gateway\PaymentGateway::class, fn () => new \App\Services\Billing\Gateway\StripeGateway(
            config('services.stripe.secret'),
            config('services.stripe.key'),
            config('services.stripe.webhook_secret'),
        ));
    }

    public function boot(): void
    {
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
        Event::listen(ModuleActivated::class, function (ModuleActivated $event) {
            if (in_array($event->module, ['stock', 'pos'], true)) {
                app(BusinessPreferenceDefaults::class)->ensure($event->business->id);
            }
        });
    }
}
