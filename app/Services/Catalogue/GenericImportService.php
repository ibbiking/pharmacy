<?php

namespace App\Services\Catalogue;

use App\Jobs\ImportGenericProductsBatch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Farmula;
use App\Models\GenericFarmula;
use App\Models\GenericProduct;
use App\Models\GenericStrength;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Strength;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Importing from the shared generic catalogue into one business (Roadmap 5.6a). Rules:
 *  - only APPROVED, non-archived entries (G-2);
 *  - a product the business already has (same name + type + strengths, ignoring order) is LINKED, not copied (G-3);
 *  - master data (manufacturer, type, strengths, formulas, units) is matched by name in the business or created;
 *  - the packaging chain is saved through ProductSetupService, so the same rules apply as on the setup screen; a missing
 *    or invalid chain leaves a DRAFT with the reason instead of a product that can't be bought or sold (G-1);
 *  - bulk imports run as a queued job right away; each item is isolated: one failure is recorded and the rest go on
 *    (G-4). The 15-minute cron is a safety sweep that also releases batches whose worker died.
 */
class GenericImportService
{
    public const STUCK_AFTER_MINUTES = 60;

    public function __construct(private readonly ProductSetupService $setup)
    {
    }

    /** @return array{status: 'imported'|'draft'|'linked'|'skipped', product_id: ?int, message: string} */
    public function importOne(GenericProduct $generic, int $businessId): array
    {
        if ($generic->status !== 'approved' || $generic->trashed()) {
            return ['status' => 'skipped', 'product_id' => null, 'message' => 'This catalogue entry isn’t approved.'];
        }

        return $this->asBusiness($businessId, function () use ($generic) {
            if ($existing = Product::query()->where('generic_product_id', $generic->id)->first()) {
                return ['status' => 'skipped', 'product_id' => $existing->id, 'message' => "“{$existing->product_name}” is already in your list."];
            }

            $generic->loadMissing(['genericCompany', 'genericType', 'parameters.parentCategory', 'parameters.childCategory']);
            $typeId = $this->local(ProductType::class, $generic->genericType?->name ?? 'General');
            $strengthIds = $this->localMany(Strength::class, GenericStrength::class, $generic->strength_id);
            $formulaIds = $this->localMany(Farmula::class, GenericFarmula::class, $generic->farmula_id);

            if ($twin = $this->twin($generic->product_name, $typeId, $strengthIds)) {
                if (! $twin->generic_product_id) {
                    $twin->forceFill(['generic_product_id' => $generic->id])->saveQuietly();
                }

                return ['status' => 'linked', 'product_id' => $twin->id, 'message' => "You already had “{$twin->product_name}”: linked to the catalogue, nothing copied."];
            }

            return DB::transaction(function () use ($generic, $typeId, $strengthIds, $formulaIds) {
                $product = Product::create([
                    'product_name' => $generic->product_name,
                    'generic_product_id' => $generic->id,
                    'company_id' => $this->local(Company::class, $generic->genericCompany?->name ?? 'Unknown'),
                    'product_type_id' => $typeId,
                    'strength_id' => $strengthIds ? implode(',', $strengthIds) : null,
                    'farmula_id' => $formulaIds ? implode(',', $formulaIds) : null,
                    'description' => $generic->description,
                    'barcode' => $generic->barcode && ! Product::query()->where('barcode', $generic->barcode)->exists() ? $generic->barcode : null,
                    'rack' => $generic->rack,
                    'is_draft' => true,
                    'sale_price_preference_id' => null,
                ]);

                $levels = $this->levels($generic);
                if ($levels === []) {
                    return ['status' => 'draft', 'product_id' => $product->id, 'message' => "“{$product->product_name}” has no packaging in the catalogue: set it up to sell it."];
                }
                try {
                    $this->setup->save($product, $levels);
                } catch (ValidationException $e) {
                    $reason = collect($e->errors())->flatten()->first() ?? 'invalid packaging';

                    return ['status' => 'draft', 'product_id' => $product->id, 'message' => "“{$product->product_name}” imported as a draft: its catalogue packaging needs fixing ({$reason})."];
                }

                return ['status' => 'imported', 'product_id' => $product->id, 'message' => "“{$product->product_name}” imported and ready to buy."];
            });
        });
    }

    /**
     * Queue approved entries for a business; already imported or already queued ones are left out.
     *
     * @param  int[]|null  $ids  null = everything still available
     * @return array{queued: int, batches: int}
     */
    public function queue(int $businessId, ?int $userId, ?array $ids): array
    {
        $query = $this->available($businessId);
        if ($ids !== null) {
            $query->whereIn('generic_products.id', array_map('intval', $ids));
        }
        $wanted = $query->pluck('generic_products.id')->all();
        if ($wanted === []) {
            return ['queued' => 0, 'batches' => 0];
        }

        $batchIds = [];
        DB::transaction(function () use ($wanted, $businessId, $userId, &$batchIds) {
            foreach (array_chunk($wanted, 200) as $chunk) {
                $batchIds[] = DB::table('generic_product_import_batches')->insertGetId([
                    'business_id' => $businessId, 'requested_by' => $userId, 'product_ids' => json_encode($chunk),
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        foreach ($batchIds as $id) {
            ImportGenericProductsBatch::dispatch($id)->afterCommit();
        }

        return ['queued' => count($wanted), 'batches' => count($batchIds)];
    }

    /** Process one batch; safe to call twice (only a pending batch is claimed). */
    public function processBatch(int $batchId): void
    {
        $claimed = DB::table('generic_product_import_batches')->where('id', $batchId)->where('status', 'pending')->whereNull('deleted_at')
            ->update(['status' => 'processing', 'started_at' => now(), 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }

        $batch = DB::table('generic_product_import_batches')->find($batchId);
        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];
        $lastError = null;

        foreach (json_decode((string) $batch->product_ids, true) ?: [] as $id) {
            try {
                $generic = GenericProduct::query()->find((int) $id);
                $result = $generic ? $this->importOne($generic, (int) $batch->business_id) : ['status' => 'skipped'];
                $counts[in_array($result['status'], ['imported', 'draft'], true) ? 'imported' : 'skipped']++;
            } catch (\Throwable $e) {
                // One bad entry must not stop the others (G-4); the error is kept for the screen and the logs.
                report($e);
                $counts['failed']++;
                $lastError = mb_substr("#{$id}: ".$e->getMessage(), 0, 500);
            }
        }

        DB::table('generic_product_import_batches')->where('id', $batchId)->update([
            'status' => 'completed', 'imported_count' => $counts['imported'], 'skipped_count' => $counts['skipped'],
            'failed_count' => $counts['failed'], 'last_error' => $lastError, 'processed_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Cron safety sweep: release batches whose worker died, then process anything still pending. */
    public function sweep(): array
    {
        $released = DB::table('generic_product_import_batches')->where('status', 'processing')
            ->where(fn ($q) => $q->whereNull('started_at')->orWhere('started_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES)))
            ->update(['status' => 'pending', 'updated_at' => now()]);

        $ids = DB::table('generic_product_import_batches')->where('status', 'pending')->whereNull('deleted_at')->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            $this->processBatch((int) $id);
        }

        return ['released' => $released, 'processed' => $ids->count()];
    }

    /** Approved catalogue entries this business has neither imported nor queued. */
    public function available(int $businessId)
    {
        $queued = $this->queuedIds($businessId);

        return GenericProduct::query()->where('generic_products.status', 'approved')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('products')->whereColumn('products.generic_product_id', 'generic_products.id')
                ->where('products.business_id', $businessId)->whereNull('products.deleted_at'))
            ->when($queued, fn ($q) => $q->whereNotIn('generic_products.id', $queued));
    }

    /** @return int[] */
    public function queuedIds(int $businessId): array
    {
        return DB::table('generic_product_import_batches')->where('business_id', $businessId)->whereIn('status', ['pending', 'processing'])
            ->whereNull('deleted_at')->pluck('product_ids')->flatMap(fn ($json) => json_decode((string) $json, true) ?: [])->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    // ------------------------------------------------------------------------------------------------ internals

    /** Packaging levels (largest first) from the catalogue's parameter rows, in ProductSetupService's format. */
    private function levels(GenericProduct $generic): array
    {
        $params = $generic->parameters;
        $root = $params->first(fn ($p) => (int) $p->parent_generic_category_id === (int) $p->child_generic_category_id);
        if (! $root || ! $root->childCategory) {
            return [];
        }

        $levels = [];
        $current = $root;
        $seen = [];
        while ($current && count($levels) < 6 && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $levels[] = [
                'category_id' => $this->local(Category::class, $current->childCategory?->name ?? '?'),
                'quantity' => $levels === [] ? 1 : (float) $current->quantity,
                'purchase_price' => $current->static_category_unit_purchase_price,
                'sale_price' => $current->static_category_unit_sale_price,
            ];
            $childId = (int) $current->child_generic_category_id;
            $current = $params->first(fn ($p) => (int) $p->parent_generic_category_id === $childId && (int) $p->child_generic_category_id !== $childId);
        }

        return $levels;
    }

    private function twin(string $name, int $typeId, array $strengthIds): ?Product
    {
        $wanted = $strengthIds;
        sort($wanted);

        return Product::query()->where('product_name', $name)->where('product_type_id', $typeId)->get()->first(function ($p) use ($wanted) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $p->strength_id))));
            sort($ids);

            return $ids === $wanted;
        });
    }

    /** Id of the business's entry with this name (archived ones are not reused), created when missing. */
    private function local(string $model, string $name): int
    {
        $name = trim($name) ?: 'Unknown';

        return (int) ($model::query()->where('name', $name)->value('id') ?? $model::create(['name' => $name])->id);
    }

    /** @return int[] */
    private function localMany(string $model, string $genericModel, ?string $csv): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $csv))));
        $names = $genericModel::withTrashed()->whereIn('id', $ids)->pluck('name', 'id'); // archived names still describe live entries

        return array_values(array_unique(array_map(fn ($id) => $this->local($model, $names[$id]), array_filter($ids, fn ($id) => isset($names[$id])))));
    }

    /**
     * Run with the session pointed at a business, so BelongsToBusiness scopes and stamps it (jobs have no session).
     * Both keys are set: BusinessScope skips scoping for a super admin unless impersonating, and an import must never
     * read or write across businesses.
     */
    private function asBusiness(int $businessId, callable $fn)
    {
        $previous = ['business_id' => session('business_id'), 'impersonate_business_id' => session('impersonate_business_id')];
        session(['business_id' => $businessId, 'impersonate_business_id' => $businessId]);
        try {
            return $fn();
        } finally {
            foreach ($previous as $key => $value) {
                $value !== null ? session([$key => $value]) : session()->forget($key);
            }
        }
    }
}
