<?php

namespace App\Http\Controllers\Admin\Resource;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * One Inertia list screen (Pages/Resource/Index.tsx) for records that are added and edited in a form: customers,
 * warehouses, accounts, expense categories … (Roadmap 7–12). A child describes the screen in schema() and maps its
 * model; this class does paging, search, sort, status filters (stat strip), create / update with server validation,
 * archive (soft delete, with an optional blocker) and restore.
 *
 * schema(): title, singular, icon, accent, help, route (route-name prefix), description, manage (permission),
 *   fields: [{key, label, type: text|email|textarea|number|money|date|switch|select|lookup, required?, options?
 *            ([{value,label}]), entity? (lookup), col? (bootstrap columns of 12), hint?, placeholder?}],
 *   columns: [{key, label, type?: text|money|number|date|pill|title, align?, sortable?}],
 *   statuses: [{key, label, icon, accent}] (the "all" and "archived" chips are added automatically),
 *   show?: route name of a profile page opened in the side drawer.
 */
abstract class ResourceController extends Controller
{
    protected const PER_PAGE = [10, 20, 50];

    abstract protected function schema(): array;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Columns searched by "q". */
    abstract protected function searchable(): array;

    /** Sortable keys => SQL column. */
    abstract protected function sortable(): array;

    /** One list row: ['cells' => [key => value], 'form' => [field => value]] (id / archived added here). */
    abstract protected function row(Model $model): array;

    /** Validation rules for create (null) or update. */
    abstract protected function rules(?Model $model): array;

    /** Base query (joins / counts); statuses filter it. */
    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /** Apply a status chip other than all / archived. */
    protected function applyStatus(Builder $query, string $status): Builder
    {
        return $query;
    }

    /** Numbers for the stat strip: [statusKey => count] (+ 'all', 'archived' added here). */
    protected function counts(): array
    {
        return [];
    }

    /** Turn validated input into model attributes. */
    protected function fill(Model $model, array $data): void
    {
        $model->fill($data);
    }

    /** A reason the record can't be archived, or null. */
    protected function archiveBlocker(Model $model): ?string
    {
        return null;
    }

    protected function afterSave(Model $model, bool $created): void
    {
    }

    protected function defaultSort(): string
    {
        return array_key_first($this->sortable());
    }

    /** Extra props for the page (e.g. settings shown above the list). */
    protected function extra(Request $request): array
    {
        return [];
    }

    public function index(Request $request)
    {
        $schema = $this->schema();
        $sortable = $this->sortable();
        $statusKeys = array_merge(array_column($schema['statuses'] ?? [], 'key'), ['archived']);
        $q = trim((string) $request->input('q', ''));
        $sort = array_key_exists($request->input('sort'), $sortable) ? $request->input('sort') : $this->defaultSort();
        $dir = $request->input('dir') === 'desc' ? 'desc' : ($request->has('dir') ? 'asc' : ($schema['default_dir'] ?? 'asc'));
        $perPage = in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20;
        $status = in_array($request->input('status'), $statusKeys, true) ? $request->input('status') : null;

        $query = $this->query();
        if ($status === 'archived') {
            $query->onlyTrashed();
        } elseif ($status) {
            $this->applyStatus($query, $status);
        }
        $rows = $query
            ->when($q !== '', fn (Builder $w) => $w->where(function ($x) use ($q) {
                foreach ($this->searchable() as $column) {
                    $x->orWhere($column, 'like', "%{$q}%");
                }
            }))
            ->orderBy($sortable[$sort], $dir)
            ->orderBy($this->query()->getModel()->getTable().'.id', $dir)
            ->paginate($perPage)->withQueryString()
            ->through(fn (Model $m) => ['id' => $m->getKey(), 'archived' => method_exists($m, 'trashed') && $m->trashed()] + $this->row($m));

        $canManage = $request->user()->can($schema['manage']);

        return Inertia::render('Resource/Index', [
            'schema' => [
                'title' => $schema['title'], 'singular' => $schema['singular'], 'icon' => $schema['icon'], 'accent' => $schema['accent'] ?? 'var(--chart-1)',
                'help' => $schema['help'] ?? null, 'route' => $schema['route'], 'description' => $schema['description'] ?? null,
                'fields' => $schema['fields'], 'columns' => $schema['columns'], 'statuses' => $schema['statuses'] ?? [],
                'show' => $schema['show'] ?? null, 'canManage' => $canManage, 'createLabel' => $schema['create_label'] ?? null,
            ],
            'rows' => $rows,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => ['all' => $this->query()->count(), 'archived' => $this->model()::onlyTrashed()->count()] + $this->counts(),
            'open' => $request->input('open') === 'create' ? 'create' : null,
        ] + $this->extra($request));
    }

    public function store(Request $request)
    {
        $model = new ($this->model());
        $this->fill($model, $request->validate($this->rules(null), [], $this->attributeNames()));
        $model->save();
        $this->afterSave($model, true);

        return back()->with('success', $this->schema()['singular'].' “'.$this->label($model).'” added.');
    }

    public function update(Request $request, int $id)
    {
        $model = $this->model()::query()->findOrFail($id);
        $this->fill($model, $request->validate($this->rules($model), [], $this->attributeNames()));
        $model->save();
        $this->afterSave($model, false);

        return back()->with('success', $this->schema()['singular'].' “'.$this->label($model).'” updated.');
    }

    public function destroy(int $id)
    {
        $model = $this->model()::query()->findOrFail($id);
        if ($reason = $this->archiveBlocker($model)) {
            throw ValidationException::withMessages(['archive' => $reason]);
        }
        $model->delete();

        return back()->with('success', $this->schema()['singular'].' “'.$this->label($model).'” archived. Restore it from the Archived filter.');
    }

    public function restore(int $id)
    {
        $model = $this->model()::onlyTrashed()->findOrFail($id);
        $model->restore();

        return back()->with('success', $this->schema()['singular'].' “'.$this->label($model).'” restored.');
    }

    protected function label(Model $model): string
    {
        return (string) ($model->name ?? $model->getKey());
    }

    protected function attributeNames(): array
    {
        return collect($this->schema()['fields'])->mapWithKeys(fn ($f) => [$f['key'] => mb_strtolower($f['label'])])->all();
    }

    protected function businessId(): ?int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id')) ?: null;
    }
}
