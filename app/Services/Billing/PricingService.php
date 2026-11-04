<?php

namespace App\Services\Billing;

use App\Models\ModulePrice;
use App\Models\PlatformSetting;
use App\Services\Modules\ModuleManager;

/** Prices a set of modules (custom plan / add-ons), adding required dependencies automatically. */
class PricingService
{
    public function __construct(private readonly ModuleManager $modules)
    {
    }

    /**
     * @param  string[]  $modules
     * @return array{modules: array<int, array{key: string, name: string, price: float, added_for_dependency: bool}>, total: float, interval: string, currency: string, free: bool}
     */
    public function quote(array $modules, string $interval = 'monthly'): array
    {
        $chosen = array_values(array_filter($modules, fn ($m) => $m !== 'core'));
        $all = [];
        foreach ($chosen as $module) {
            foreach ($this->modules->withDependencies($module) as $key) {
                $all[$key] = true;
            }
        }
        unset($all['core']);

        $prices = ModulePrice::query()->whereIn('module', array_keys($all))->get()->keyBy('module');
        $column = $interval === 'yearly' ? 'yearly_price' : 'monthly_price';
        $free = PlatformSetting::freeForEveryone();

        $lines = [];
        foreach (array_keys($all) as $key) {
            $lines[] = [
                'key' => $key,
                'name' => $this->modules->definition($key)['name'],
                'price' => $free ? 0.0 : (float) ($prices[$key]->{$column} ?? 0),
                'added_for_dependency' => ! in_array($key, $chosen, true),
            ];
        }

        return [
            'modules' => $lines,
            'total' => round(array_sum(array_column($lines, 'price')), 2),
            'interval' => $interval === 'yearly' ? 'yearly' : 'monthly',
            'currency' => $prices->first()->currency ?? 'USD',
            'free' => $free,
        ];
    }
}
