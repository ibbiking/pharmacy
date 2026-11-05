<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\Modules\ModuleManager;
use App\Support\BusinessTypes;
use Inertia\Inertia;

/** Public landing page (Roadmap 5.0): what the product does, business types, modules, pricing, sign-up. */
class WelcomeController extends Controller
{
    public function __invoke(ModuleManager $modules)
    {
        return Inertia::render('Welcome', [
            'brand' => config('app.brand', 'PharmaSuite'),
            'types' => BusinessTypes::forScreens(),
            'modules' => collect($modules->all())->map(fn ($m, $key) => [
                'key' => $key, 'name' => $m['name'], 'description' => $m['description'], 'icon' => $m['icon'],
                'accent' => $m['accent'], 'available' => (bool) ($m['available'] ?? true), 'core' => (bool) ($m['core'] ?? false),
            ])->values(),
            'plans' => Plan::query()->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->limit(4)->get()
                ->map(fn (Plan $p) => [
                    'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'currency' => $p->currency,
                    'monthly_price' => $p->monthly_price, 'trial_days' => $p->trial_days,
                    'modules' => array_values(array_map(fn ($k) => $modules->definition($k)['name'], array_filter($p->modules, fn ($k) => $modules->exists($k) && $k !== 'core'))),
                ]),
            'free' => PlatformSetting::freeForEveryone(),
            'signedIn' => auth()->check(),
        ]);
    }
}
