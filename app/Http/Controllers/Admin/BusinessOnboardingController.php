<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Currency\GlobalCurrency;
use App\Models\PosCartReservation;
use App\Services\Modules\ModuleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/**
 * Create-business wizard (Roadmap step 4c): any logged-in user can create a business at any time
 * (first login, or "Create new business" from the workspace menu). The creator becomes its owner.
 */
class BusinessOnboardingController extends Controller
{
    public const TYPES = [
        'pharmacy' => [
            'name' => 'Pharmacy',
            'icon' => 'capsule-pill',
            'description' => 'Medicines with batches & expiry, generic medicine catalogue, POS with FEFO, FBR-ready receipts.',
        ],
        'inventory' => [
            'name' => 'Inventory',
            'icon' => 'box-seam',
            'description' => 'Shops and stores selling items: stock, purchases, POS, multi-warehouse (coming soon).',
        ],
        'inventory_b2b' => [
            'name' => 'Inventory B2B',
            'icon' => 'briefcase',
            'description' => 'Wholesale & distribution: quotations, sales/purchase orders, accounting and expenses (rolling out in stages).',
        ],
    ];

    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function create(Request $request)
    {
        $modules = collect($this->modules->all())->map(fn ($m, $key) => [
            'key' => $key,
            'name' => $m['name'],
            'description' => $m['description'],
            'icon' => $m['icon'],
            'accent' => $m['accent'],
            'core' => (bool) ($m['core'] ?? false),
            'available' => (bool) ($m['available'] ?? true),
            'depends_on' => $m['depends_on'] ?? [],
        ])->values();

        $types = collect(self::TYPES)->map(fn ($t, $key) => $t + [
            'key' => $key,
            'bundle' => $this->modules->bundleFor($key),
        ])->values();

        return Inertia::render('Business/Create', [
            'types' => $types,
            'modules' => $modules,
            'isFirstBusiness' => ! $request->user()->businesses()->exists(),
            'defaultCurrency' => GlobalCurrency::query()->whereNull('business_id')->where('currency_code', 'PKR')->first(['id', 'currency_code', 'name', 'symbol']),
        ]);
    }

    /** Paginated currency search for the wizard (global currencies only: the business doesn't exist yet). */
    public function currencies(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = GlobalCurrency::query()->whereNull('business_id')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('currency_code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->orderByRaw("currency_code = 'PKR' desc")->orderBy('currency_code')
            ->paginate(20, ['id', 'currency_code', 'name', 'symbol']);

        return response()->json([
            'data' => collect($page->items())->map(fn ($c) => ['id' => $c->id, 'label' => "{$c->currency_code} — {$c->name} ({$c->symbol})"]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    public function store(Request $request)
    {
        $available = collect($this->modules->all())->filter(fn ($m) => ($m['available'] ?? true) && ! ($m['core'] ?? false))->keys()->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')->whereNull('business_id')],
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in($available)],
        ]);

        $user = $request->user();

        $business = DB::transaction(function () use ($data, $user) {
            // Business::created switches on the type's default bundle; we then apply the user's choice.
            $business = Business::create([
                'name' => $data['name'],
                'business_type' => $data['business_type'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'created_by' => $user->id,
            ]);
            $user->businesses()->attach($business->id, ['role' => 'owner']);
            if (! $user->hasRole('super-admin') && ! $user->hasRole('Business Owner')) {
                $user->assignRole(Role::findOrCreate('Business Owner'));
            }

            if (\App\Models\PlatformSetting::freeForEveryone()) {
                $this->applyModuleChoice($business, $data['modules'] ?? [], $user->id);
            }

            return $business;
        });

        // Work in the new business straight away (an open POS cart belongs to the previous one).
        if ($previous = session('business_id')) {
            PosCartReservation::releaseForUser($user->id, (int) $previous);
        }
        $request->session()->forget('pos_cart');
        session(['business_id' => $business->id]);

        $message = "“{$business->name}” is ready.";

        // Launch pricing over: the chosen modules are paid for on the Billing page (Core is free).
        if (! \App\Models\PlatformSetting::freeForEveryone() && ! empty($data['modules'])) {
            return redirect()->route('billing.index', ['modules' => array_values($data['modules'])])
                ->with('success', $message.' Choose how to pay for your modules to switch them on.');
        }

        return $this->modules->isActive($business->id, 'pharmacy')
            ? redirect()->route('generic_products.index')->with('success', $message.' Import medicines from the generic catalogue to get started.')
            : redirect()->route('dashboard')->with('success', $message);
    }

    /** Final set = chosen modules + what they depend on; everything else (except core) is switched off. */
    private function applyModuleChoice(Business $business, array $chosen, int $by): void
    {
        $target = ['core'];
        foreach ($chosen as $module) {
            $target = array_merge($target, $this->modules->withDependencies($module));
        }
        $target = array_values(array_unique($target));

        foreach ($target as $module) {
            if ($module !== 'core') {
                $this->modules->activate($business, $module, 'free', $by);
            }
        }

        // Switch off unchosen modules, dependents first (repeat until nothing is left to switch off).
        do {
            $changed = false;
            foreach ($this->modules->activeFor($business->id) as $module) {
                if (in_array($module, $target, true) || $this->modules->dependents($business->id, $module)) {
                    continue;
                }
                $this->modules->deactivate($business, $module);
                $changed = true;
            }
        } while ($changed);
    }
}
