<?php

namespace App\Support;

use App\Models\User;
use App\Services\Modules\ModuleManager;
use Illuminate\Support\Facades\Route;

/** Builds the sidebar for a user from config/navigation.php (permissions, roles, wording, active state). */
class Navigation
{
    private static ?int $businessId = null;
    private static bool $bypassModules = false;

    public static function for(?User $user, array $wording, ?string $currentRoute, ?int $businessId = null): array
    {
        if (! $user) {
            return [];
        }
        self::$businessId = $businessId;
        self::$bypassModules = $user->hasRole('super-admin') && ! session()->has('impersonate_business_id');

        $groups = [];
        foreach (config('navigation', []) as $group) {
            $items = array_values(array_filter(array_map(
                fn ($item) => self::item($item, $user, $wording, $currentRoute),
                $group['items']
            )));
            if ($items) {
                $groups[] = ['group' => $group['group'], 'items' => $items];
            }
        }

        return $groups;
    }

    private static function item(array $item, User $user, array $wording, ?string $currentRoute): ?array
    {
        if (! self::allowed($item, $user)) {
            return null;
        }

        $children = [];
        foreach ($item['children'] ?? [] as $child) {
            if ($resolved = self::item($child, $user, $wording, $currentRoute)) {
                $children[] = $resolved;
            }
        }

        if (isset($item['children']) && ! $children) {
            return null;
        }
        if (! isset($item['children']) && (! isset($item['route']) || ! Route::has($item['route']))) {
            return null;
        }

        $active = isset($item['route']) && $currentRoute === $item['route'];
        foreach ($children as $child) {
            $active = $active || $child['active'];
        }

        // Screens of a module the business has not switched on are shown locked → module store.
        $locked = false;
        if (isset($item['route'])) {
            $module = app(ModuleManager::class)->moduleForRoute($item['route']);
            $locked = $module && self::$businessId && ! self::$bypassModules
                && ! app(ModuleManager::class)->isActive(self::$businessId, $module);
        } elseif ($children) {
            $locked = collect($children)->every(fn ($c) => $c['locked'] ?? false);
        }

        return array_filter([
            'label' => self::label($item['label'], $wording),
            'icon' => $item['icon'] ?? null,
            'href' => $locked && isset($item['route'])
                ? route('modules.index', ['module' => app(ModuleManager::class)->moduleForRoute($item['route'])])
                : (isset($item['route']) ? route($item['route']) : null),
            'inertia' => $locked ? true : (bool) ($item['inertia'] ?? false),
            'active' => $active,
            'locked' => $locked ?: null,
            'children' => $children ?: null,
        ], fn ($v) => $v !== null);
    }

    private static function allowed(array $item, User $user): bool
    {
        if (isset($item['role']) && ! $user->hasRole($item['role'])) {
            return false;
        }
        if (($item['owner'] ?? false) && ! $user->isOwnerOf(self::$businessId)) {
            return false;
        }
        if (isset($item['can']) && ! $user->hasRole('super-admin') && ! $user->can($item['can'])) {
            return false;
        }

        return true;
    }

    private static function label(string $label, array $wording): string
    {
        return str_starts_with($label, 'wording:') ? ($wording[substr($label, 8)] ?? $label) : $label;
    }
}
