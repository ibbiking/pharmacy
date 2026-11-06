<?php

namespace App\Services\Catalogue;

use App\Models\Company;
use App\Models\Farmula;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Strength;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Add / edit a product's details (Roadmap 5.4b). One place for the rules:
 *  - a picker value is either {id} (an entry of this business) or {name} (typed new: reused when this business already
 *    has an active entry with that name, created otherwise), so a numeric name like "500" is never mistaken for an id;
 *  - name + strengths + type is unique within the business, ignoring archived products and the order of strengths;
 *  - a barcode identifies one active product of the business (the POS scans it);
 *  - the per-product discount cap only applies when "lock" is on (see SalePreferenceResolver).
 * Packaging and prices are the setup wizard's job (ProductSetupService).
 */
class ProductFormService
{
    public function save(?Product $product, array $input): Product
    {
        $data = validator($input, [
            'product_name' => ['required', 'string', 'max:200'],
            'company' => ['required', 'array'],
            'product_type' => ['required', 'array'],
            'strengths' => ['nullable', 'array', 'max:10'],
            'formulas' => ['nullable', 'array', 'max:20'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'rack' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'lock_max_discount' => ['boolean'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [], [
            'product_name' => 'name', 'company' => 'manufacturer', 'product_type' => 'type', 'discount' => 'maximum discount amount',
            'discount_percent' => 'maximum discount %',
        ])->validate();

        $name = trim($data['product_name']);
        $barcode = trim((string) ($data['barcode'] ?? '')) ?: null;

        return DB::transaction(function () use ($product, $data, $name, $barcode) {
            $companyId = $this->resolve(Company::class, $data['company'], 'company');
            $typeId = $this->resolve(ProductType::class, $data['product_type'], 'product_type');
            $strengthIds = $this->resolveMany(Strength::class, $data['strengths'] ?? [], 'strengths');
            $formulaIds = $this->resolveMany(Farmula::class, $data['formulas'] ?? [], 'formulas');

            $this->assertUnique($product, $name, $typeId, $strengthIds);
            if ($barcode !== null && Product::query()->where('barcode', $barcode)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists()) {
                throw ValidationException::withMessages(['barcode' => 'Another product already has this barcode.']);
            }

            $lock = (bool) ($data['lock_max_discount'] ?? false);
            $attributes = [
                'product_name' => $name,
                'company_id' => $companyId,
                'product_type_id' => $typeId,
                'strength_id' => $strengthIds ? implode(',', $strengthIds) : null,
                'farmula_id' => $formulaIds ? implode(',', $formulaIds) : null,
                'barcode' => $barcode,
                'rack' => trim((string) ($data['rack'] ?? '')) ?: null,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'lock_max_discount' => $lock,
                'discount' => $lock ? round((float) ($data['discount'] ?? 0), 2) : 0,
                'discount_percent' => $lock ? round((float) ($data['discount_percent'] ?? 0), 2) : 0,
            ];

            if ($product) {
                $product->update($attributes);
            } else {
                $product = Product::create($attributes + ['is_draft' => true, 'sale_price_preference_id' => null]);
            }

            \App\Services\GenericProductService::syncProductToGeneric($product);

            return $product;
        });
    }

    /** Archiving is blocked while sellable stock remains (it would vanish from the POS but still be counted). */
    public function archiveBlocker(Product $product): ?string
    {
        $stock = (float) \App\Models\BaseStockSalePrice::query()->where('product_id', $product->id)->where('remaining_base_stock', '>', 0)
            ->where(fn ($w) => $w->whereNull('expiry_date')->orWhereDate('expiry_date', '>', today()))->sum('remaining_base_stock');

        return $stock > 0 ? "“{$product->product_name}” still has stock. Sell, return or write it off first, then archive it." : null;
    }

    public function restoreBlocker(Product $product): ?string
    {
        try {
            $this->assertUnique($product, $product->product_name, (int) $product->product_type_id,
                array_values(array_filter(array_map('intval', explode(',', (string) $product->strength_id)))));
        } catch (ValidationException) {
            return "An active “{$product->product_name}” with the same strength and type already exists, so this one can’t be restored.";
        }

        return null;
    }

    private function assertUnique(?Product $product, string $name, int $typeId, array $strengthIds): void
    {
        $wanted = $strengthIds;
        sort($wanted);
        $clash = Product::query()->where('product_name', $name)->where('product_type_id', $typeId)
            ->when($product, fn ($q) => $q->whereKeyNot($product->id))
            ->pluck('strength_id')
            ->contains(function ($csv) use ($wanted) {
                $ids = array_values(array_filter(array_map('intval', explode(',', (string) $csv))));
                sort($ids);

                return $ids === $wanted;
            });

        if ($clash) {
            throw ValidationException::withMessages(['product_name' => 'You already have this product with the same strength and type.']);
        }
    }

    /** @param class-string<Model> $model */
    private function resolve(string $model, array $value, string $field): int
    {
        if (! empty($value['id'])) {
            $row = $model::query()->find((int) $value['id']); // business-scoped, archived excluded
            if (! $row) {
                throw ValidationException::withMessages([$field => 'This entry no longer exists. Pick another one.']);
            }

            return (int) $row->id;
        }

        $name = trim((string) ($value['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages([$field => 'Pick an entry or type a new name (up to 100 characters).']);
        }

        return (int) ($model::query()->where('name', $name)->value('id') ?? $model::create(['name' => $name])->id);
    }

    /** @param class-string<Model> $model @return int[] */
    private function resolveMany(string $model, array $values, string $field): array
    {
        $ids = [];
        foreach (array_values($values) as $value) {
            $ids[] = $this->resolve($model, is_array($value) ? $value : [], $field);
        }

        return array_values(array_unique($ids));
    }
}
