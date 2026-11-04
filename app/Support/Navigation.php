<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/** Builds the sidebar for a user from config/navigation.php (permissions, roles, wording, active state). */
class Navigation
{
    public static function for(?User $user, array $wording, ?string $currentRoute): array
    {
        if (! $user) {
            return [];
        }

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

        return array_filter([
            'label' => self::label($item['label'], $wording),
            'icon' => $item['icon'] ?? null,
            'href' => isset($item['route']) ? route($item['route']) : null,
            'inertia' => (bool) ($item['inertia'] ?? false),
            'active' => $active,
            'children' => $children ?: null,
        ], fn ($v) => $v !== null);
    }

    private static function allowed(array $item, User $user): bool
    {
        if (isset($item['role']) && ! $user->hasRole($item['role'])) {
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
