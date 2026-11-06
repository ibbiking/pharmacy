<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Models\GenericFarmula;
use App\Models\GenericProduct;
use App\Models\GenericStrength;
use App\Models\Product;
use App\Services\Catalogue\GenericImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Generic catalogue, business side (Roadmap 5.6a): browse the shared, super-admin-curated list and import from it.
 * A super admin (not impersonating) sees every status here and manages entries on the suggestions screen (5.6b).
 */
class GenericCatalogController extends Controller
{
    public function __construct(private readonly GenericImportService $imports)
    {
    }

    public function index(Request $request)
    {
        $admin = $this->isPlatformAdmin($request);
        $businessId = (int) session('business_id');
        $q = trim((string) $request->input('q', ''));
        $sort = in_array($request->input('sort'), ['product_name', 'created_at'], true) ? $request->input('sort') : 'product_name';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $statuses = $admin ? ['approved', 'pending', 'rejected', 'archived'] : ['available', 'mine', 'queued', 'suggested'];
        $status = in_array($request->input('status'), $statuses, true) ? $request->input('status') : null;
        $queued = $admin ? [] : $this->imports->queuedIds($businessId);
        $mine = fn (Builder $w, bool $in) => $w->{$in ? 'whereExists' : 'whereNotExists'}(fn ($x) => $x->selectRaw('1')->from('products')
            ->whereColumn('products.generic_product_id', 'generic_products.id')->where('products.business_id', $businessId)->whereNull('products.deleted_at'));

        $query = ($status === 'archived' ? GenericProduct::onlyTrashed() : GenericProduct::query())
            ->with(['genericCompany:id,name', 'genericType:id,name'])
            ->when(! $admin && $status !== 'suggested', fn ($w) => $w->where('status', 'approved'))
            ->when($status === 'suggested', fn ($w) => $w->where('suggested_by_business_id', $businessId))
            ->when($admin && in_array($status, ['approved', 'pending', 'rejected'], true), fn ($w) => $w->where('status', $status))
            ->when($status === 'available', fn ($w) => $mine($w, false)->when($queued, fn ($x) => $x->whereNotIn('id', $queued)))
            ->when($status === 'mine', fn ($w) => $mine($w, true))
            ->when($status === 'queued', fn ($w) => $w->whereIn('id', $queued ?: [0]))
            ->when($q !== '', fn ($w) => $this->search($w, $q));

        $page = $query->orderBy($sort, $dir)->orderBy('id')->paginate($perPage)->withQueryString();
        $rows = $page->getCollection();
        $inList = $admin ? collect() : Product::query()->whereIn('generic_product_id', $rows->pluck('id'))->pluck('id', 'generic_product_id');
        $names = $this->names($rows);
        $page->setCollection($rows->map(fn (GenericProduct $g) => [
            'id' => $g->id,
            'name' => $g->product_name,
            'company' => $g->genericCompany?->name,
            'type' => $g->genericType?->name,
            'strengths' => $this->pick($names['strengths'], $g->strength_id),
            'formulas' => $this->pick($names['formulas'], $g->farmula_id),
            'status' => $g->trashed() ? 'archived' : $g->status,
            'product_id' => $inList[$g->id] ?? null,
            'queued' => in_array($g->id, $queued, true),
            'editable' => $admin || ($g->status === 'pending' && (int) $g->suggested_by_business_id === $businessId),
        ]));

        return Inertia::render('Generic/Index', [
            'rows' => $page,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => $admin ? [
                'total' => GenericProduct::count(), 'approved' => GenericProduct::where('status', 'approved')->count(),
                'pending' => GenericProduct::where('status', 'pending')->count(), 'rejected' => GenericProduct::where('status', 'rejected')->count(),
                'archived' => GenericProduct::onlyTrashed()->count(),
            ] : [
                'total' => GenericProduct::where('status', 'approved')->count(),
                'available' => $this->imports->available($businessId)->count(),
                'mine' => $mine(GenericProduct::where('status', 'approved'), true)->count(),
                'queued' => count($queued),
                'suggested' => GenericProduct::where('suggested_by_business_id', $businessId)->count(),
            ],
            'batches' => fn () => $admin ? [] : DB::table('generic_product_import_batches')->where('business_id', $businessId)->whereNull('deleted_at')
                ->orderByDesc('id')->limit(5)->get()->map(fn ($b) => [
                    'id' => $b->id, 'status' => $b->status, 'items' => count(json_decode((string) $b->product_ids, true) ?: []),
                    'imported' => (int) $b->imported_count, 'skipped' => (int) $b->skipped_count, 'failed' => (int) ($b->failed_count ?? 0),
                    'error' => $b->last_error ?? null, 'at' => \Illuminate\Support\Carbon::parse($b->created_at)->diffForHumans(),
                ]),
            'admin' => $admin,
            'canImport' => ! $admin && $request->user()->can('pharmacy.generic.import'),
        ]);
    }

    /** Details for the drawer (JSON). */
    public function details(Request $request, $id)
    {
        $admin = $this->isPlatformAdmin($request);
        $g = GenericProduct::withTrashed()->with(['genericCompany', 'genericType', 'parameters.parentCategory', 'parameters.childCategory'])
            ->when(! $admin, fn ($w) => $w->where('status', 'approved')->whereNull('deleted_at'))->findOrFail($id);
        $names = $this->names(collect([$g]));

        // The chain, largest unit first (same walk as the import).
        $params = $g->parameters;
        $chain = [];
        $current = $params->first(fn ($p) => (int) $p->parent_generic_category_id === (int) $p->child_generic_category_id);
        while ($current && count($chain) < 8) {
            $chain[] = ['unit' => $current->childCategory?->name ?? '?', 'quantity' => $chain === [] ? 1 : (float) $current->quantity,
                'purchase_price' => $current->static_category_unit_purchase_price !== null ? (float) $current->static_category_unit_purchase_price : null,
                'sale_price' => $current->static_category_unit_sale_price !== null ? (float) $current->static_category_unit_sale_price : null];
            $childId = (int) $current->child_generic_category_id;
            $current = $params->first(fn ($p) => (int) $p->parent_generic_category_id === $childId && (int) $p->child_generic_category_id !== $childId);
        }

        return response()->json([
            'id' => $g->id, 'name' => $g->product_name, 'company' => $g->genericCompany?->name, 'type' => $g->genericType?->name,
            'strengths' => $this->pick($names['strengths'], $g->strength_id), 'formulas' => $this->pick($names['formulas'], $g->farmula_id),
            'barcode' => $g->barcode, 'description' => $g->description, 'status' => $g->trashed() ? 'archived' : $g->status, 'chain' => $chain,
            'product_id' => $admin ? null : Product::query()->where('generic_product_id', $g->id)->value('id'),
        ]);
    }

    public function import(Request $request)
    {
        $request->validate(['product_id' => ['required', 'integer']]);
        $generic = GenericProduct::query()->where('status', 'approved')->find($request->integer('product_id'));
        if (! $generic) {
            return $this->reply($request, 'error', 'This catalogue entry isn’t available to import.', 404);
        }

        try {
            $result = $this->imports->importOne($generic, (int) session('business_id'));
        } catch (\Throwable $e) {
            report($e);

            return $this->reply($request, 'error', 'The import failed. Please try again; if it keeps failing, tell support (catalogue #'.$generic->id.').', 500);
        }

        return $this->reply($request, $result['status'] === 'skipped' ? 'error' : 'success', $result['message'], 200, ['product_id' => $result['product_id'], 'status' => $result['status']]);
    }

    public function bulkImport(Request $request)
    {
        $data = $request->validate(['product_ids' => ['required', 'array', 'min:1', 'max:500'], 'product_ids.*' => ['integer']],
            ['product_ids.required' => 'Select at least one product.', 'product_ids.max' => 'Select at most 500 at a time, or use “Import everything”.']);

        return $this->queued($request, $this->imports->queue((int) session('business_id'), $request->user()->id, $data['product_ids']));
    }

    public function importAll(Request $request)
    {
        return $this->queued($request, $this->imports->queue((int) session('business_id'), $request->user()->id, null));
    }

    // ------------------------------------------------------------------------------------------------ helpers

    private function queued(Request $request, array $r)
    {
        return $r['queued'] === 0
            ? $this->reply($request, 'error', 'Nothing new to import: everything selected is already in your list or being imported.', 422)
            : $this->reply($request, 'success', "{$r['queued']} product(s) are being imported. This page updates as they arrive.");
    }

    private function reply(Request $request, string $kind, string $message, int $status = 200, array $extra = [])
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => $kind === 'success', 'message' => $message] + $extra, $kind === 'success' ? 200 : $status);
        }

        return back()->with($kind, $message);
    }

    private function isPlatformAdmin(Request $request): bool
    {
        return $request->user()->hasRole('super-admin') && ! session()->has('impersonate_business_id');
    }

    /** Name, barcode, manufacturer, formula or strength. */
    private function search(Builder $w, string $q): Builder
    {
        $like = "%{$q}%";
        $strengthIds = GenericStrength::withTrashed()->where('name', 'like', $like)->limit(50)->pluck('id');
        $formulaIds = GenericFarmula::withTrashed()->where('name', 'like', $like)->limit(50)->pluck('id');
        $inCsv = function ($x, string $col, $ids) {
            foreach ($ids as $id) {
                $x->orWhereRaw("FIND_IN_SET(?, {$col})", [$id]);
            }
        };

        return $w->where(function ($x) use ($like, $q, $strengthIds, $formulaIds, $inCsv) {
            $x->where('product_name', 'like', $like)->orWhere('barcode', $q)
                ->orWhereHas('genericCompany', fn ($c) => $c->where('name', 'like', $like));
            $inCsv($x, 'strength_id', $strengthIds);
            $inCsv($x, 'farmula_id', $formulaIds);
        });
    }

    private function names($rows): array
    {
        $ids = fn (string $col) => $rows->flatMap(fn ($r) => array_filter(array_map('intval', explode(',', (string) $r->{$col}))))->unique()->values();

        return [
            'strengths' => GenericStrength::withTrashed()->whereIn('id', $ids('strength_id'))->pluck('name', 'id'),
            'formulas' => GenericFarmula::withTrashed()->whereIn('id', $ids('farmula_id'))->pluck('name', 'id'),
        ];
    }

    private function pick($names, ?string $csv): array
    {
        return array_values(array_filter(array_map(fn ($id) => $names[(int) $id] ?? null, explode(',', (string) $csv))));
    }
}
