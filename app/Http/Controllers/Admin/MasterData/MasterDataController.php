<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\BusinessWording;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * One Inertia screen for every simple "name + description" master-data list (Roadmap step 5):
 * manufacturers/brands, dosage forms/item types, strengths, formulas. Each child only describes its
 * entity in definition(). Server-paginated list, stat strip (all / in use / not used / archived),
 * modal create/edit, archive (blocked while products use it) and restore.
 * Names are unique per business, ignoring archived rows.
 */
abstract class MasterDataController extends Controller
{
    /**
     * @return array{model: class-string<Model>, route: string, wording: array{0: string, 1: string}, icon: string,
     *               accent: string, fk: string, max: int, help?: string|null, examples: string}
     */
    abstract protected function definition(): array;

    public function index(Request $request)
    {
        $d = $this->definition();
        $model = new $d['model'];
        $table = $model->getTable();
        $q = trim((string) $request->input('q', ''));
        $sort = in_array($request->input('sort'), ['name', 'usage', 'created_at'], true) ? $request->input('sort') : 'name';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $status = in_array($request->input('status'), ['used', 'unused', 'archived'], true) ? $request->input('status') : null;

        $rows = $this->withUsage($status === 'archived' ? $d['model']::onlyTrashed() : $d['model']::query())
            ->when($q !== '', fn (Builder $w) => $w->where(fn ($x) => $x->where("{$table}.name", 'like', "%{$q}%")->orWhere("{$table}.description", 'like', "%{$q}%")))
            ->when($status === 'used', fn (Builder $w) => $w->having('usage', '>', 0))
            ->when($status === 'unused', fn (Builder $w) => $w->having('usage', '=', 0))
            ->orderBy($sort === 'usage' ? 'usage' : "{$table}.{$sort}", $dir)->orderBy("{$table}.id")
            ->paginate($perPage)->withQueryString()
            ->through(fn (Model $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'description' => $m->description,
                'usage' => (int) $m->usage,
                'created_at' => $m->created_at?->format('d M Y'),
                'archived' => method_exists($m, 'trashed') && $m->trashed(),
            ]);

        $type = BusinessWording::for(\App\Models\Business::find(session('impersonate_business_id') ?? session('business_id'))?->business_type);

        return Inertia::render('MasterData/Index', [
            'entity' => [
                'key' => $d['route'],
                'singular' => $type[$d['wording'][0]] ?? ucfirst($d['wording'][0]),
                'plural' => $type[$d['wording'][1]] ?? ucfirst($d['wording'][1]),
                'products' => $type['products'] ?? 'Products',
                'icon' => $d['icon'],
                'accent' => $d['accent'],
                'max' => $d['max'],
                'help' => $d['help'] ?? null,
                'examples' => $d['examples'],
                'canManage' => $request->user()->can($this->managePermission()),
            ],
            'rows' => $rows,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => $this->stats(),
            'open' => $request->input('open') === 'create' ? 'create' : ($request->integer('open') ?: null),
        ]);
    }

    /** Old Blade links (/x/create, /x/{id}/edit) open the modal on the list. */
    public function create()
    {
        return redirect()->route($this->definition()['route'].'.index', ['open' => 'create']);
    }

    public function edit(Request $request, $id)
    {
        return redirect()->route($this->definition()['route'].'.index', ['open' => (int) $id]);
    }

    public function store(Request $request)
    {
        $d = $this->definition();
        $data = $this->validated($request);
        $row = $d['model']::create($data);

        return $this->done($request, "“{$row->name}” added.");
    }

    public function update(Request $request, $id)
    {
        $d = $this->definition();
        $row = $d['model']::findOrFail($id);
        $row->update($this->validated($request, $row->id));

        return $this->done($request, "“{$row->name}” saved.");
    }

    public function destroy(Request $request, $id = null)
    {
        $d = $this->definition();
        $row = $d['model']::findOrFail($id ?? $request->input('id'));
        $usage = $this->usageOf((int) $row->id);
        if ($usage > 0) {
            $products = BusinessWording::for(\App\Models\Business::find(session('business_id'))?->business_type)['products'] ?? 'products';

            return $this->fail($request, "“{$row->name}” is used by {$usage} ".strtolower($products).'. Change them first, then archive it.');
        }
        $row->delete();

        return $this->done($request, "“{$row->name}” archived. You can restore it from the Archived filter.");
    }

    public function restore(Request $request, $id)
    {
        $d = $this->definition();
        $row = $d['model']::onlyTrashed()->findOrFail($id);
        if ($d['model']::query()->where('name', $row->name)->exists()) {
            return $this->fail($request, "An active “{$row->name}” already exists, so this one can’t be restored.");
        }
        $row->restore();

        return $this->done($request, "“{$row->name}” restored.");
    }

    /** Select2 search used by the classic product form until it moves to Inertia (paged, 10 per page). */
    public function autocompleteName(Request $request)
    {
        $page = $this->definition()['model']::query()
            ->where('name', 'like', '%'.trim((string) $request->get('term', '')).'%')
            ->select('name')->distinct()->orderBy('name')->simplePaginate(10);

        return response()->json([
            'results' => collect($page->items())->map(fn ($r) => ['id' => $r->name, 'text' => $r->name])->values(),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    protected function managePermission(): string
    {
        return 'core.catalogue.manage';
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $d = $this->definition();
        $table = (new $d['model'])->getTable();
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.$d['max'],
                Rule::unique($table, 'name')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'You already have this name. Names must be unique (archived ones don’t count).',
        ]);
        $data['name'] = trim($data['name']);

        return $data;
    }

    /**
     * Sub-query counting the products that use the row whose id is in $idColumn. Default: products.<fk>.
     * Override when usage lives elsewhere (e.g. packaging is linked through product_categories).
     */
    protected function usageSubquery(string $idColumn): Builder
    {
        return Product::query()->selectRaw('count(*)')->whereColumn('products.'.$this->definition()['fk'], $idColumn);
    }

    private function withUsage(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->select("{$table}.*")->selectSub($this->usageSubquery("{$table}.id"), 'usage');
    }

    private function usageOf(int $id): int
    {
        $d = $this->definition();
        $table = (new $d['model'])->getTable();

        return (int) $this->withUsage($d['model']::query()->whereKey($id))->value('usage');
    }

    private function stats(): array
    {
        $d = $this->definition();
        $table = (new $d['model'])->getTable();
        $sub = $this->usageSubquery("{$table}.id")->toBase();
        $total = $d['model']::query()->count();
        $used = $d['model']::query()->whereRaw('('.$sub->toSql().') > 0', $sub->getBindings())->count();

        return [
            'total' => $total,
            'used' => $used,
            'unused' => $total - $used,
            'archived' => $d['model']::onlyTrashed()->count(),
            'added_30d' => $d['model']::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    private function done(Request $request, string $message)
    {
        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    private function fail(Request $request, string $message)
    {
        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['message' => $message], 422)
            : back()->with('error', $message);
    }
}
