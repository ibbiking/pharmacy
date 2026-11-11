<?php

namespace App\Services\Stock;

use App\Models\Inventory\Warehouse;
use App\Services\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;

/**
 * Warehouses (Inventory Pro, Roadmap step 7).
 * - Without Inventory Pro every lot has warehouse_id NULL and nothing here filters anything.
 * - Switching Inventory Pro on creates "Main warehouse" (default) and moves every existing lot and purchase of the
 *   business into it (`ensureDefault`, idempotent; run by the ModuleActivated listener).
 * - New purchases go to the chosen warehouse or the default one.
 * - The POS sells from the "POS warehouse" preference (inventory / pos-warehouse), or from every warehouse when unset.
 */
class WarehouseService
{
    private array $posCache = [];

    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function enabled(?int $businessId): bool
    {
        return $businessId !== null && $this->modules->isActive($businessId, 'inventory_pro');
    }

    /** Create the default warehouse when the business has none, and put unassigned stock in it. */
    public function ensureDefault(int $businessId): int
    {
        $default = Warehouse::withoutGlobalScopes()->where('business_id', $businessId)->whereNull('deleted_at')
            ->orderByDesc('is_default')->orderBy('id')->first();
        if (! $default) {
            $default = new Warehouse(['name' => 'Main warehouse', 'code' => 'MAIN', 'is_default' => true]);
            $default->business_id = $businessId;
            $default->save();
        } elseif (! $default->is_default) {
            $default->forceFill(['is_default' => true])->save();
        }

        DB::table('base_stock_sale_price')->where('business_id', $businessId)->whereNull('warehouse_id')->update(['warehouse_id' => $default->id]);
        DB::table('purchases')->where('business_id', $businessId)->whereNull('warehouse_id')->update(['warehouse_id' => $default->id]);

        return (int) $default->id;
    }

    /** Default warehouse id, or null when Inventory Pro is off. */
    public function defaultId(?int $businessId): ?int
    {
        if (! $this->enabled($businessId)) {
            return null;
        }

        return (int) (Warehouse::withoutGlobalScopes()->where('business_id', $businessId)->whereNull('deleted_at')->where('is_default', true)->value('id')
            ?? $this->ensureDefault($businessId));
    }

    /** A warehouse of this business (for a picked id) or the default one. */
    public function resolve(?int $businessId, $warehouseId): ?int
    {
        if (! $this->enabled($businessId)) {
            return null;
        }
        if ($warehouseId) {
            $id = Warehouse::withoutGlobalScopes()->where('business_id', $businessId)->whereNull('deleted_at')->whereKey((int) $warehouseId)->value('id');
            if (! $id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['warehouse_id' => 'Pick one of your warehouses.']);
            }

            return (int) $id;
        }

        return $this->defaultId($businessId);
    }

    /** The warehouse the POS sells from (null = every warehouse). */
    public function posWarehouseId(?int $businessId): ?int
    {
        if (! $this->enabled($businessId)) {
            return null;
        }
        if (! array_key_exists($businessId, $this->posCache)) {
            $value = DB::table('preferences')->where('business_id', $businessId)->where('type', 'inventory')->where('slug', 'pos-warehouse')
                ->whereNull('deleted_at')->value('value');
            $this->posCache[$businessId] = $value && Warehouse::withoutGlobalScopes()->where('business_id', $businessId)->whereNull('deleted_at')->whereKey((int) $value)->exists()
                ? (int) $value : null;
        }

        return $this->posCache[$businessId];
    }

    public function setPosWarehouse(int $businessId, ?int $warehouseId): void
    {
        $now = now();
        $exists = DB::table('preferences')->where('business_id', $businessId)->where('type', 'inventory')->where('slug', 'pos-warehouse')->whereNull('deleted_at')->exists();
        if ($exists) {
            DB::table('preferences')->where('business_id', $businessId)->where('type', 'inventory')->where('slug', 'pos-warehouse')->whereNull('deleted_at')
                ->update(['value' => $warehouseId, 'status' => $warehouseId !== null, 'updated_at' => $now]);
        } else {
            DB::table('preferences')->insert(['business_id' => $businessId, 'type' => 'inventory', 'slug' => 'pos-warehouse', 'preference' => 'POS sells from warehouse',
                'status' => $warehouseId !== null, 'value' => $warehouseId, 'created_at' => $now, 'updated_at' => $now]);
        }
        unset($this->posCache[$businessId]);
    }
}
