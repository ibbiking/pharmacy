<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Admin\Resource\ResourceController;
use App\Models\Inventory\Warehouse;
use App\Services\Stock\WarehouseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Warehouses (Inventory Pro, Roadmap step 7): one default; stock value per warehouse; the POS warehouse setting. */
class WarehouseController extends ResourceController
{
    protected function schema(): array
    {
        return [
            'title' => 'Warehouses', 'singular' => 'Warehouse', 'icon' => 'buildings', 'accent' => 'var(--chart-6)', 'help' => 'inventory-pro',
            'route' => 'warehouses', 'manage' => 'inventory.manage',
            'description' => 'Where your stock is kept. Purchases go to a warehouse, transfers move stock between them, the POS sells from one.',
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'col' => 8, 'placeholder' => 'e.g. Main store'],
                ['key' => 'code', 'label' => 'Code', 'type' => 'text', 'col' => 4, 'placeholder' => 'e.g. WH-2'],
                ['key' => 'address', 'label' => 'Address', 'type' => 'textarea'],
                ['key' => 'is_default', 'label' => 'Default warehouse (new purchases go here)', 'type' => 'switch'],
            ],
            'columns' => [
                ['key' => 'name', 'label' => 'Warehouse', 'type' => 'title', 'sortable' => true],
                ['key' => 'role', 'label' => ''],
                ['key' => 'lots', 'label' => 'Batches in stock', 'type' => 'number', 'align' => 'end', 'sortable' => true],
                ['key' => 'value', 'label' => 'Stock value (cost)', 'type' => 'money', 'align' => 'end', 'sortable' => true],
            ],
            'statuses' => [['key' => 'stocked', 'label' => 'With stock', 'icon' => 'box-seam', 'accent' => 'var(--app-success)']],
        ];
    }

    protected function model(): string
    {
        return Warehouse::class;
    }

    protected function searchable(): array
    {
        return ['warehouses.name', 'warehouses.code', 'warehouses.address'];
    }

    protected function sortable(): array
    {
        return ['name' => 'warehouses.name', 'lots' => 'lots', 'value' => 'value'];
    }

    protected function query(): Builder
    {
        $lots = fn () => DB::table('base_stock_sale_price as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')
            ->whereColumn('l.warehouse_id', 'warehouses.id')->whereNull('l.deleted_at')->where('l.remaining_base_stock', '>', 0);

        return Warehouse::query()->select('warehouses.*')
            ->selectSub($lots()->selectRaw('count(*)'), 'lots')
            ->selectSub($lots()->selectRaw('coalesce(sum(l.remaining_base_stock * (case when p.paid_extra_total_cost_price > 0 then p.paid_extra_total_cost_price else p.total_cost_price end) / nullif(p.base_quantity, 0)), 0)'), 'value');
    }

    protected function applyStatus(Builder $query, string $status): Builder
    {
        return $query->having('lots', '>', 0);
    }

    protected function counts(): array
    {
        return ['stocked' => Warehouse::query()->whereExists(fn ($q) => $q->from('base_stock_sale_price')->whereColumn('base_stock_sale_price.warehouse_id', 'warehouses.id')
            ->whereNull('base_stock_sale_price.deleted_at')->where('remaining_base_stock', '>', 0))->count()];
    }

    protected function row(Model $m): array
    {
        $pos = app(WarehouseService::class)->posWarehouseId($this->businessId());

        return [
            'cells' => [
                'name' => $m->name, 'name_sub' => trim(($m->code ? "{$m->code} · " : '').($m->address ?? ''), ' ·') ?: null,
                'role' => $m->is_default ? ['text' => 'Default', 'accent' => 'var(--app-primary)'] : ($pos === $m->id ? ['text' => 'POS', 'accent' => 'var(--chart-3)'] : null),
                'lots' => (int) $m->lots, 'value' => round((float) $m->value, 2),
            ],
            'form' => ['name' => $m->name, 'code' => $m->code, 'address' => $m->address, 'is_default' => (bool) $m->is_default],
            'locked' => $m->is_default ? 'The default warehouse can’t be archived: make another one the default first.' : ((int) $m->lots > 0 ? 'It still holds stock: move it out first.' : null),
        ];
    }

    protected function rules(?Model $model): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'name')->where('business_id', $this->businessId())->whereNull('deleted_at')->ignore($model?->id)],
            'code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ];
    }

    protected function fill(Model $model, array $data): void
    {
        $model->fill(['name' => trim($data['name']), 'code' => $data['code'] ?? null, 'address' => $data['address'] ?? null, 'is_default' => (bool) ($data['is_default'] ?? false) || ! Warehouse::query()->where('is_default', true)->whereKeyNot($model->id ?? 0)->exists()]);
    }

    protected function afterSave(Model $model, bool $created): void
    {
        if ($model->is_default) {
            Warehouse::query()->whereKeyNot($model->id)->where('is_default', true)->update(['is_default' => false]);
        }
    }

    protected function archiveBlocker(Model $model): ?string
    {
        if ($model->is_default) {
            return 'The default warehouse can’t be archived: make another one the default first.';
        }

        return DB::table('base_stock_sale_price')->where('warehouse_id', $model->id)->whereNull('deleted_at')->where('remaining_base_stock', '>', 0)->exists()
            ? 'It still holds stock: move it out first.' : null;
    }

    protected function extra(Request $request): array
    {
        $service = app(WarehouseService::class);
        $pos = $service->posWarehouseId($this->businessId());
        $posWarehouse = $pos ? Warehouse::find($pos) : null;

        return [
            'currency' => currency_symbol(),
            'picker' => [
                'label' => 'The POS sells from', 'hint' => 'Leave empty to sell from every warehouse (FEFO across all of them).',
                'entity' => 'warehouses', 'field' => 'warehouse_id', 'route' => route('warehouses.pos'), 'placeholder' => 'Every warehouse',
                'value' => $posWarehouse ? ['value' => $posWarehouse->id, 'label' => $posWarehouse->name] : null,
                'canChange' => $request->user()->can('inventory.manage'),
            ],
        ];
    }

    public function setPos(Request $request, WarehouseService $warehouses)
    {
        $data = $request->validate(['warehouse_id' => ['nullable', 'integer']]);
        $id = $data['warehouse_id'] ? Warehouse::query()->whereKey($data['warehouse_id'])->value('id') : null;
        abort_if($data['warehouse_id'] && ! $id, 422, 'Pick one of your warehouses.');
        $warehouses->setPosWarehouse($this->businessId(), $id ? (int) $id : null);

        return back()->with('success', $id ? 'The POS now sells from this warehouse only.' : 'The POS now sells from every warehouse.');
    }
}
