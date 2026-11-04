<?php

namespace App\Support;

/**
 * Display wording per business type (Pharmacy / Inventory / Inventory B2B). Screens never hard-code
 * "Medicine" or "Item"; they read this dictionary (shared to React as the `wording` Inertia prop).
 */
class BusinessWording
{
    public const TYPES = ['pharmacy', 'inventory', 'inventory_b2b'];
    public const DEFAULT_TYPE = 'pharmacy';

    public static function for(?string $type): array
    {
        $type = in_array($type, self::TYPES, true) ? $type : self::DEFAULT_TYPE;

        return config("business_types.{$type}", []);
    }
}
