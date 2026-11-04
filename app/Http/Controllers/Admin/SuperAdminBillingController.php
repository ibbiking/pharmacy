<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Services\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Super admin: module prices, plans and launch pricing (Roadmap step 4d). */
class SuperAdminBillingController extends Controller
{
    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function index(Request $request)
    {
        $sellable = $this->sellable();
        $prices = ModulePrice::query()->whereIn('module', array_keys($sellable))->get()->keyBy('module');
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;

        return Inertia::render('SuperAdmin/Billing', [
            // A fixed, small list (one row per module in config), not a growing table.
            'modulePrices' => collect($sellable)->map(fn ($m, $key) => [
                'module' => $key,
                'name' => $m['name'],
                'icon' => $m['icon'],
                'accent' => $m['accent'],
                'available' => (bool) ($m['available'] ?? true),
                'monthly_price' => (float) ($prices[$key]->monthly_price ?? 0),
                'yearly_price' => (float) ($prices[$key]->yearly_price ?? 0),
                'currency' => $prices[$key]->currency ?? 'USD',
            ])->values(),
            'plans' => Plan::query()
                ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
                ->orderBy('sort_order')->orderBy('id')
                ->paginate($perPage)->withQueryString()
                ->through(fn (Plan $p) => $p->only(['id', 'name', 'slug', 'description', 'modules', 'monthly_price', 'yearly_price', 'currency', 'limits', 'trial_days', 'is_visible', 'sort_order'])),
            'filters' => ['q' => $q, 'per_page' => $perPage],
            'settings' => [
                'free_for_everyone' => (bool) PlatformSetting::get('free_for_everyone', true),
                'free_grace_until' => PlatformSetting::get('free_grace_until'),
                'free_now' => PlatformSetting::freeForEveryone(),
            ],
            'limitKeys' => Plan::LIMITS,
        ]);
    }

    public function updateModulePrice(Request $request, string $module)
    {
        abort_unless(array_key_exists($module, $this->sellable()), 404);
        $data = $request->validate([
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
        ]);
        $data['currency'] = strtoupper($data['currency']);

        ModulePrice::updateOrCreate(['module' => $module], $data);

        return back()->with('success', $this->modules->definition($module)['name'].' price saved.');
    }

    public function storePlan(Request $request)
    {
        Plan::create($this->validatedPlan($request));

        return back()->with('success', 'Plan created.');
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $plan->update($this->validatedPlan($request, $plan));

        return back()->with('success', 'Plan updated.');
    }

    public function destroyPlan(Plan $plan)
    {
        $plan->delete();

        return back()->with('success', "Plan “{$plan->name}” archived.");
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'free_for_everyone' => ['required', 'boolean'],
            'free_grace_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        PlatformSetting::put('free_for_everyone', (bool) $data['free_for_everyone']);
        PlatformSetting::put('free_grace_until', $data['free_for_everyone'] ? null : ($data['free_grace_until'] ?? null));

        return back()->with('success', $data['free_for_everyone'] ? 'Launch pricing is ON: every module is free.' : 'Launch pricing is OFF: modules now need a plan.');
    }

    private function validatedPlan(Request $request, ?Plan $plan = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('name', ''))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(array_keys($this->sellable()))],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'limits' => ['nullable', 'array'],
            'limits.*' => ['nullable', 'integer', 'min:1'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'is_visible' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        // A plan always contains what its modules depend on.
        $modules = [];
        foreach ($data['modules'] as $module) {
            $modules = array_merge($modules, $this->modules->withDependencies($module));
        }
        $data['modules'] = array_values(array_diff(array_unique($modules), ['core']));
        $data['currency'] = strtoupper($data['currency']);
        $data['limits'] = collect($data['limits'] ?? [])->only(Plan::LIMITS)->map(fn ($v) => $v === null || $v === '' ? null : (int) $v)->all() ?: null;
        $data['sort_order'] ??= 0;

        return $data;
    }

    /** Every module except Core (which is always included). */
    private function sellable(): array
    {
        return array_filter($this->modules->all(), fn ($m) => ! ($m['core'] ?? false));
    }
}
