<?php

namespace App\Services\Catalogue;

use App\Models\GenericCategory;
use App\Models\GenericCompany;
use App\Models\GenericFarmula;
use App\Models\GenericProduct;
use App\Models\GenericProductCategory;
use App\Models\GenericProductParameter;
use App\Models\GenericProductType;
use App\Models\GenericStrength;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shared generic catalogue entries (Roadmap 5.6b). Who may change what (G-7, G-9):
 *  - a super admin (not impersonating) may add, edit, approve and reject any entry;
 *  - a business may suggest entries and edit ITS OWN suggestion only while it is pending.
 * A super admin's new entries are approved at once; a business's are pending. Names a business types (manufacturer,
 * type, strengths, formulas, units) become pending too and are approved together with the entry.
 * The packaging chain follows the same rules as a product's setup (ProductSetupService) and is stored in the shape the
 * import reads: one root row (parent = child) plus one row per link, and the matching hierarchy rows.
 */
class GenericEntryService
{
    public function isAdmin(?User $user): bool
    {
        return $user && $user->hasRole('super-admin') && ! session()->has('impersonate_business_id');
    }

    public function businessId(): ?int
    {
        $id = session('impersonate_business_id') ?? session('business_id');

        return $id ? (int) $id : null;
    }

    public function canEdit(GenericProduct $entry, ?User $user): bool
    {
        return $this->isAdmin($user)
            || ($entry->status === 'pending' && $this->businessId() && (int) $entry->suggested_by_business_id === $this->businessId());
    }

    /** @return array<string, mixed> data for the editor */
    public function present(GenericProduct $g): array
    {
        $opt = fn (?Model $m) => $m ? ['value' => $m->id, 'label' => $m->name] : null;
        $many = fn (string $model, ?string $csv) => collect(array_filter(array_map('intval', explode(',', (string) $csv))))
            ->map(fn ($id) => $model::withTrashed()->find($id))->filter()->map($opt)->values()->all();

        return [
            'id' => $g->id, 'product_name' => $g->product_name, 'status' => $g->status,
            'company' => $opt($g->genericCompany), 'type' => $opt($g->genericType),
            'strengths' => $many(GenericStrength::class, $g->strength_id), 'formulas' => $many(GenericFarmula::class, $g->farmula_id),
            'barcode' => $g->barcode, 'description' => $g->description,
            'levels' => $this->chain($g),
        ];
    }

    /** Packaging levels, largest unit first. */
    public function chain(GenericProduct $g): array
    {
        $params = $g->parameters()->with(['childCategory'])->get();
        $levels = [];
        $current = $params->first(fn ($p) => (int) $p->parent_generic_category_id === (int) $p->child_generic_category_id);
        while ($current && count($levels) < 6) {
            $levels[] = ['unit' => $current->childCategory ? ['value' => $current->childCategory->id, 'label' => $current->childCategory->name] : null,
                'quantity' => $levels === [] ? 1.0 : (float) $current->quantity,
                'purchase_price' => $current->static_category_unit_purchase_price !== null ? (float) $current->static_category_unit_purchase_price : null,
                'sale_price' => $current->static_category_unit_sale_price !== null ? (float) $current->static_category_unit_sale_price : null];
            $childId = (int) $current->child_generic_category_id;
            $current = $params->first(fn ($p) => (int) $p->parent_generic_category_id === $childId && (int) $p->child_generic_category_id !== $childId);
        }

        return $levels;
    }

    public function save(?GenericProduct $entry, array $input, ?User $user): GenericProduct
    {
        $admin = $this->isAdmin($user);
        if ($entry && ! $this->canEdit($entry, $user)) {
            throw ValidationException::withMessages(['entry' => $entry->status === 'pending'
                ? 'Only the business that suggested this entry, or a platform admin, can change it.'
                : 'Approved catalogue entries can only be changed by a platform admin.']);
        }

        $data = validator($input, [
            'product_name' => ['required', 'string', 'max:200'],
            'company' => ['required', 'array'],
            'type' => ['required', 'array'],
            'strengths' => ['nullable', 'array', 'max:10'],
            'formulas' => ['nullable', 'array', 'max:20'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'levels' => ['nullable', 'array', 'max:6'],
        ], [], ['product_name' => 'name', 'company' => 'manufacturer', 'type' => 'type'])->validate();
        $levels = array_values($data['levels'] ?? []);

        return DB::transaction(function () use ($entry, $data, $levels, $admin) {
            $status = $admin ? 'approved' : 'pending';
            $companyId = $this->resolve(GenericCompany::class, $data['company'], 'company', $status);
            $typeId = $this->resolve(GenericProductType::class, $data['type'], 'type', $status);
            $strengthIds = array_values(array_unique(array_map(fn ($r) => $this->resolve(GenericStrength::class, (array) $r, 'strengths', $status), $data['strengths'] ?? [])));
            $formulaIds = array_values(array_unique(array_map(fn ($r) => $this->resolve(GenericFarmula::class, (array) $r, 'formulas', $status), $data['formulas'] ?? [])));
            $chain = $this->checkLevels($levels, $status);

            $name = trim($data['product_name']);
            if ($this->duplicate($entry, $name, $typeId, $strengthIds, $formulaIds)) {
                throw ValidationException::withMessages(['product_name' => 'The catalogue already has this product with the same type, strengths and formulas.']);
            }

            $attributes = [
                'product_name' => $name, 'generic_company_id' => $companyId, 'generic_product_type_id' => $typeId,
                'strength_id' => $strengthIds ? implode(',', $strengthIds) : null, 'farmula_id' => $formulaIds ? implode(',', $formulaIds) : null,
                'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null, 'description' => trim((string) ($data['description'] ?? '')) ?: null,
            ];
            if ($entry) {
                $entry->update($attributes);
            } else {
                $entry = GenericProduct::create($attributes + [
                    'status' => $status, 'approved_by' => $admin ? auth()->id() : null,
                    'suggested_by_business_id' => $admin ? null : $this->businessId(),
                ]);
            }
            $this->writeChain($entry, $chain);

            return $entry->fresh();
        });
    }

    /** Approve entries and the pending names they use. @return int approved */
    public function approve(array $ids, ?User $user): int
    {
        $this->mustBeAdmin($user);

        return DB::transaction(function () use ($ids, $user) {
            $entries = GenericProduct::query()->whereIn('id', $ids)->where('status', '!=', 'approved')->get();
            foreach ($entries as $g) {
                $g->update(['status' => 'approved', 'approved_by' => $user->id]);
                $pendingNames = fn (string $model, array $nameIds) => $model::query()->whereIn('id', $nameIds)->where('status', 'pending')->update(['status' => 'approved']);
                $pendingNames(GenericCompany::class, [$g->generic_company_id]);
                $pendingNames(GenericProductType::class, [$g->generic_product_type_id]);
                $pendingNames(GenericStrength::class, array_filter(explode(',', (string) $g->strength_id)));
                $pendingNames(GenericFarmula::class, array_filter(explode(',', (string) $g->farmula_id)));
                $pendingNames(GenericCategory::class, GenericProductParameter::where('generic_product_id', $g->id)->pluck('child_generic_category_id')->all());
            }

            return $entries->count();
        });
    }

    public function reject(array $ids, ?User $user): int
    {
        $this->mustBeAdmin($user);

        return GenericProduct::query()->whereIn('id', $ids)->where('status', 'pending')->update(['status' => 'rejected', 'updated_at' => now()]);
    }

    // ------------------------------------------------------------------------------------------------ internals

    private function mustBeAdmin(?User $user): void
    {
        if (! $this->isAdmin($user)) {
            throw ValidationException::withMessages(['entry' => 'Only a platform admin can review catalogue suggestions.']);
        }
    }

    /** {id} of a visible name, or {name}: reuse an existing name, else create it (pending for a business). */
    private function resolve(string $model, array $ref, string $field, string $status): int
    {
        if (! empty($ref['id'])) {
            $row = $model::query()->visibleInCatalogue()->find((int) $ref['id']);
            if (! $row) {
                throw ValidationException::withMessages([$field => 'Pick an entry from the list, or type a new name.']);
            }

            return (int) $row->id;
        }
        $name = trim((string) ($ref['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages([$field => 'Pick an entry from the list, or type a new name (up to 100 characters).']);
        }
        if ($existing = $model::query()->where('name', $name)->first()) {
            return (int) $existing->id;
        }

        return (int) $model::create(['name' => $name, 'status' => $status, 'suggested_by_business_id' => $status === 'pending' ? $this->businessId() : null])->id;
    }

    /** Same rules as a product's packaging (ProductSetupService::save). */
    private function checkLevels(array $levels, string $status): array
    {
        $out = [];
        $errors = [];
        foreach ($levels as $i => $l) {
            $l = (array) $l;
            try {
                $unitId = $this->resolve(GenericCategory::class, (array) ($l['unit'] ?? []), "levels.{$i}.unit", $status);
            } catch (ValidationException $e) {
                $errors["levels.{$i}.unit"] = 'Pick or type a unit.';
                continue;
            }
            $qty = $i === 0 ? 1 : ($l['quantity'] ?? null);
            if (! is_numeric($qty) || (float) $qty <= 0 || (float) $qty > 100000 || floor((float) $qty) != (float) $qty) {
                $errors["levels.{$i}.quantity"] = 'Enter how many fit in the unit above (a whole number above 0).';
            }
            $price = function (string $f) use ($l, $i, &$errors) {
                $v = $l[$f] ?? null;
                if ($v === null || $v === '') {
                    return null;
                }
                if (! is_numeric($v) || (float) $v < 0 || (float) $v > 100000000) {
                    $errors["levels.{$i}.{$f}"] = 'Enter a price of 0 or more.';

                    return null;
                }

                return round((float) $v, 4);
            };
            $buy = $price('purchase_price');
            $sell = $price('sale_price');
            if ($buy && $sell && $buy >= $sell) {
                $errors["levels.{$i}.sale_price"] = 'The sale price must be higher than the purchase price.';
            }
            $out[] = ['unit_id' => $unitId, 'quantity' => (int) $qty, 'purchase_price' => $buy, 'sale_price' => $sell];
        }
        $ids = array_column($out, 'unit_id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors['levels'] = 'Each unit can appear only once in the packaging.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    private function writeChain(GenericProduct $entry, array $chain): void
    {
        // Old rows are archived (soft delete), never erased.
        GenericProductParameter::where('generic_product_id', $entry->id)->get()->each->delete();
        GenericProductCategory::where('generic_product_id', $entry->id)->get()->each->delete();
        if ($chain === []) {
            return;
        }
        $root = $chain[0]['unit_id'];
        foreach ($chain as $i => $l) {
            $parent = $i === 0 ? $l['unit_id'] : $chain[$i - 1]['unit_id'];
            GenericProductParameter::create([
                'generic_product_id' => $entry->id, 'generic_category_id' => $root, 'parent_generic_category_id' => $parent,
                'child_generic_category_id' => $l['unit_id'], 'quantity' => $i === 0 ? 1 : $l['quantity'],
                'static_category_unit_purchase_price' => $l['purchase_price'], 'static_category_unit_sale_price' => $l['sale_price'],
            ]);
            if ($i > 0 || count($chain) === 1) {
                GenericProductCategory::create(['generic_product_id' => $entry->id, 'parent_generic_category_id' => $parent, 'child_generic_category_id' => $l['unit_id']]);
            }
        }
    }

    private function duplicate(?GenericProduct $entry, string $name, int $typeId, array $strengthIds, array $formulaIds): bool
    {
        $norm = function (?string $csv) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $csv))));
            sort($ids);

            return $ids;
        };
        $s = $strengthIds;
        sort($s);
        $f = $formulaIds;
        sort($f);

        return GenericProduct::query()->where('product_name', $name)->where('generic_product_type_id', $typeId)->where('status', '!=', 'rejected')
            ->when($entry, fn ($q) => $q->whereKeyNot($entry->id))->get(['strength_id', 'farmula_id'])
            ->contains(fn ($g) => $norm($g->strength_id) === $s && $norm($g->farmula_id) === $f);
    }
}
