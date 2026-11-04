<?php

namespace App\Services\Modules;

use App\Events\ModuleActivated;
use App\Models\Business;
use App\Models\BusinessModule;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Which modules a business has switched on, and switching them on/off (with dependencies).
 * Registry: config/modules.php. Storage: business_modules.
 */
class ModuleManager
{
    /** @var array<int, array<string, bool>> businessId => [module => true] (per request) */
    private array $active = [];

    public function all(): array
    {
        return config('modules.modules', []);
    }

    public function exists(string $module): bool
    {
        return isset($this->all()[$module]);
    }

    public function definition(string $module): array
    {
        return $this->all()[$module] ?? throw new InvalidArgumentException("Unknown module [{$module}].");
    }

    /** Modules switched on for the business (core is always on). */
    public function activeFor(int $businessId): array
    {
        if (! isset($this->active[$businessId])) {
            $keys = BusinessModule::query()->where('business_id', $businessId)->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->pluck('module')->all();
            $this->active[$businessId] = array_fill_keys(array_merge(['core'], $keys), true);
        }

        return array_keys($this->active[$businessId]);
    }

    public function isActive(int $businessId, string $module): bool
    {
        if ($module === 'core') {
            return true;
        }
        $this->activeFor($businessId);

        return isset($this->active[$businessId][$module]);
    }

    /** Default modules for a business type ("Pharmacy" → core, stock, pos, pharmacy). */
    public function bundleFor(string $type): array
    {
        return config("modules.bundles.{$type}", config('modules.bundles.pharmacy'));
    }

    /** The module that owns a route name, or null (= Core). */
    public function moduleForRoute(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }
        foreach ($this->all() as $key => $module) {
            foreach ($module['routes'] ?? [] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Modules that must also be switched on for $module (transitive). */
    public function withDependencies(string $module): array
    {
        $result = [];
        $walk = function (string $key) use (&$walk, &$result) {
            foreach ($this->definition($key)['depends_on'] ?? [] as $dep) {
                if (! in_array($dep, $result, true)) {
                    $walk($dep);
                    $result[] = $dep;
                }
            }
        };
        $walk($module);
        $result[] = $module;

        return $result;
    }

    /** Active modules that depend (directly or not) on $module. */
    public function dependents(int $businessId, string $module): array
    {
        return array_values(array_filter($this->activeFor($businessId), fn ($key) => $key !== $module
            && $key !== 'core' && in_array($module, $this->withDependencies($key), true)));
    }

    /**
     * Switch a module (and anything it depends on) on for a business. Idempotent.
     *
     * @return string[] modules that were newly activated
     */
    public function activate(Business $business, string $module, string $source = 'free', ?int $by = null): array
    {
        $definition = $this->definition($module);
        if (! ($definition['available'] ?? true)) {
            throw new RuntimeException("{$definition['name']} is not available yet.");
        }

        $newly = [];
        foreach ($this->withDependencies($module) as $key) {
            if ($key === 'core' || $this->isActive($business->id, $key)) {
                continue;
            }
            BusinessModule::updateOrCreate(
                ['business_id' => $business->id, 'module' => $key],
                ['status' => 'active', 'source' => $source, 'activated_at' => now(), 'expires_at' => null, 'activated_by' => $by],
            );
            $newly[] = $key;
        }

        unset($this->active[$business->id]);
        foreach ($newly as $key) {
            ModuleActivated::dispatch($business, $key);
        }

        return $newly;
    }

    /** Switch a module off. Data is kept and becomes visible again when re-activated. */
    public function deactivate(Business $business, string $module): void
    {
        $definition = $this->definition($module);
        if ($definition['core'] ?? false) {
            throw new RuntimeException('Core cannot be switched off.');
        }
        if ($dependents = $this->dependents($business->id, $module)) {
            $names = implode(', ', array_map(fn ($k) => $this->definition($k)['name'], $dependents));
            throw new RuntimeException("Switch off {$names} first: they need {$definition['name']}.");
        }

        BusinessModule::query()->where('business_id', $business->id)->where('module', $module)->get()->each->delete();
        unset($this->active[$business->id]);
    }

    public function forget(?int $businessId = null): void
    {
        if ($businessId === null) {
            $this->active = [];
        } else {
            unset($this->active[$businessId]);
        }
    }
}
