<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Services\Modules\ModuleManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The business's module store: see every module, switch modules on/off.
 * Free while launch pricing is on; afterwards a module is switched on through its subscription (Billing page).
 */
class ModuleController extends Controller
{
    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function index(Request $request)
    {
        $business = $this->business();
        $active = BusinessModule::query()->where('business_id', $business->id)->get()->keyBy('module');
        $prices = \App\Models\ModulePrice::query()->get()->keyBy('module');

        $list = collect($this->modules->all())->map(function ($m, $key) use ($active, $business, $prices) {
            $row = $active->get($key);

            return [
                'key' => $key,
                'name' => $m['name'],
                'description' => $m['description'],
                'icon' => $m['icon'],
                'accent' => $m['accent'],
                'core' => (bool) ($m['core'] ?? false),
                'available' => (bool) ($m['available'] ?? true),
                'active' => $key === 'core' || $this->modules->isActive($business->id, $key),
                'source' => $row?->source,
                'activated_at' => $row?->activated_at?->format('d M Y'),
                'depends_on' => array_map(fn ($d) => $this->modules->definition($d)['name'], $m['depends_on'] ?? []),
                'in_bundle' => in_array($key, $this->modules->bundleFor($business->business_type), true),
                'monthly_price' => isset($prices[$key]) ? (float) $prices[$key]->monthly_price : null,
                'currency' => $prices[$key]->currency ?? null,
            ];
        })->values();

        return Inertia::render('Modules/Index', [
            'modules' => $list,
            'highlight' => $request->input('module'),
            'canManage' => $this->canManage($request, $business),
            'businessType' => $business->business_type,
            'free' => \App\Models\PlatformSetting::freeForEveryone(),
        ]);
    }

    public function activate(Request $request, string $module)
    {
        $business = $this->business();
        abort_unless($this->canManage($request, $business), 403);
        abort_unless($this->modules->exists($module), 404);

        $source = 'free';
        if (! \App\Models\PlatformSetting::freeForEveryone()) {
            // Paid: allowed when the subscription already covers it; otherwise add it on the Billing page.
            $sub = \App\Models\Billing\Subscription::currentFor($business->id);
            if (! $sub || ! $sub->isEntitled() || ! in_array($module, $sub->modules ?? [], true)) {
                $name = $this->modules->definition($module)['name'];

                return redirect()->route('billing.index', ['modules' => [$module]])
                    ->with('info', $sub && $sub->isEntitled() ? "Add {$name} to your plan to switch it on." : "Choose a plan with {$name} to switch it on.");
            }
            $source = 'subscription';
        }

        try {
            $newly = $this->modules->activate($business, $module, $source, $request->user()->id);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $names = implode(', ', array_map(fn ($k) => $this->modules->definition($k)['name'], $newly));

        return back()->with('success', $newly ? "Switched on: {$names}." : 'Already active.');
    }

    public function deactivate(Request $request, string $module)
    {
        $business = $this->business();
        abort_unless($this->canManage($request, $business), 403);
        abort_unless($this->modules->exists($module), 404);

        try {
            $this->modules->deactivate($business, $module);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $this->modules->definition($module)['name'].' switched off. Its data is kept.');
    }

    private function business(): Business
    {
        return Business::findOrFail((int) (session('impersonate_business_id') ?? session('business_id')));
    }

    /** Only the business owner (or a super-admin) changes what the business has. */
    private function canManage(Request $request, Business $business): bool
    {
        return $request->user()->can('core.settings.manage');
    }
}
