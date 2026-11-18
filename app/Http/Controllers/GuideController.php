<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Services\Modules\ModuleManager;
use App\Support\BusinessTypes;
use Inertia\Inertia;

/**
 * Public product guide (/guide, landing navbar "Guide"): business types, modules, selling & buying, explained for
 * end users. Types, modules, starting bundles and wording come from the same config the app uses, so the page can't
 * drift from the product. Owner's Q&A behind it: docs/PRODUCT_GUIDE.md.
 */
class GuideController extends Controller
{
    /** Words shown in the "same thing, different name" table. */
    private const WORDS = ['product', 'category', 'company', 'formula', 'strength', 'product_type', 'generic_catalog', 'supplier', 'customer', 'store'];

    public function __invoke(ModuleManager $modules)
    {
        $types = BusinessTypes::forScreens();

        return Inertia::render('Guide', [
            'brand' => config('app.brand', 'PharmaSuite'),
            'types' => $types,
            'modules' => collect($modules->all())->map(fn ($m, $key) => [
                'key' => $key, 'name' => $m['name'], 'description' => $m['description'], 'icon' => $m['icon'], 'accent' => $m['accent'],
                'available' => (bool) ($m['available'] ?? true), 'core' => (bool) ($m['core'] ?? false),
                'needs' => array_values(array_map(fn ($d) => $modules->definition($d)['name'], $m['depends_on'] ?? [])),
                'starts_in' => collect($types)->filter(fn ($t) => in_array($key, $t['bundle'], true))->pluck('key')->values(),
                // One business = one type: which types may have it at all, and for which it is part of the type itself.
                'allowed_in' => collect($types)->filter(fn ($t) => in_array($key, $t['allowed'], true))->pluck('key')->values(),
                'locked_in' => collect($types)->filter(fn ($t) => in_array($key, $t['locked'], true))->pluck('key')->values(),
            ])->values(),
            'wording' => collect($types)->mapWithKeys(fn ($t) => [$t['key'] => collect(self::WORDS)
                ->mapWithKeys(fn ($w) => [$w => config("business_types.{$t['key']}.{$w}")])])->all(),
            'free' => PlatformSetting::freeForEveryone(),
            'signedIn' => auth()->check(),
        ]);
    }
}
