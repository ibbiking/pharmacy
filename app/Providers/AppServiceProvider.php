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
    }

    public function boot(): void
    {
        // Event discovery is off (bootstrap/app.php): listeners are registered explicitly here.
        Event::listen(ModuleActivated::class, function (ModuleActivated $event) {
            if (in_array($event->module, ['stock', 'pos'], true)) {
                app(BusinessPreferenceDefaults::class)->ensure($event->business->id);
            }
        });
    }
}
