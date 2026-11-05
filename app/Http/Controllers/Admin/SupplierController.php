<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Suppliers (Inertia, Roadmap step 5): list with purchase totals, profile with history and a 12-month chart,
 * modal add/edit, archive (history keeps the name) and restore. Names are unique per business.
 */
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $sort = in_array($request->input('sort'), ['name', 'purchases_count', 'purchased_total', 'last_purchase_at', 'created_at'], true) ? $request->input('sort') : 'name';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $status = in_array($request->input('status'), ['active', 'idle', 'archived'], true) ? $request->input('status') : null;

        $rows = $this->withTotals($status === 'archived' ? Supplier::onlyTrashed() : Supplier::query())
            ->when($q !== '', fn (Builder $w) => $w->where(fn ($x) => $x->where('suppliers.name', 'like', "%{$q}%")
                ->orWhere('suppliers.phone', 'like', "%{$q}%")->orWhere('suppliers.email', 'like', "%{$q}%")->orWhere('suppliers.company', 'like', "%{$q}%")))
            ->when($status === 'active', fn (Builder $w) => $w->having('purchases_count', '>', 0))
            ->when($status === 'idle', fn (Builder $w) => $w->having('purchases_count', '=', 0))
            ->orderBy(in_array($sort, ['name', 'created_at'], true) ? "suppliers.{$sort}" : $sort, $dir)->orderBy('suppliers.id')
            ->paginate($perPage)->withQueryString()
            ->through(fn (Supplier $s) => $this->present($s));

        return Inertia::render('Suppliers/Index', [
            'rows' => $rows,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => [
                'total' => Supplier::query()->count(),
                'active' => Supplier::query()->whereHas('purchases')->count(),
                'archived' => Supplier::onlyTrashed()->count(),
                'purchased_30d' => round((float) Purchase::query()->whereNotNull('supplier_id')->where('created_at', '>=', now()->subDays(30))->sum('total_cost_price'), 2),
            ],
            'currency' => currency_symbol(),
            'canManage' => $request->user()->can('core.catalogue.manage'),
            'open' => $request->input('open') === 'create' ? 'create' : ($request->integer('open') ?: null),
        ]);
    }

    public function show(Request $request, $id)
    {
        $supplier = $this->withTotals(Supplier::withTrashed())->whereKey($id)->firstOrFail();
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 10;
        $q = trim((string) $request->input('q', ''));

        $purchases = Purchase::query()->where('supplier_id', $supplier->id)
            ->with(['product:id,product_name', 'category:id,name'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('batch_no', 'like', "%{$q}%")->orWhere('invoice_no', 'like', "%{$q}%")
                ->orWhereHas('product', fn ($p) => $p->where('product_name', 'like', "%{$q}%"))))
            ->latest('id')->paginate($perPage)->withQueryString()
            ->through(fn (Purchase $p) => [
                'id' => $p->id,
                'product' => $p->product?->product_name ?? '—',
                'unit' => $p->category?->name,
                'quantity' => (float) $p->quantity,
                'unit_cost' => (float) $p->unit_cost_price,
                'total' => (float) $p->total_cost_price,
                'batch' => $p->batch_no,
                'invoice' => $p->invoice_no,
                'expiry' => $p->expiry_date ? \Illuminate\Support\Carbon::parse($p->expiry_date)->format('d M Y') : null,
                'expired' => $p->expiry_date && \Illuminate\Support\Carbon::parse($p->expiry_date)->isPast(),
                'date' => $p->created_at?->format('d M Y'),
            ]);

        // Purchases per month for the last 12 months (one grouped query).
        $from = now()->startOfMonth()->subMonths(11);
        $byMonth = Purchase::query()->where('supplier_id', $supplier->id)->where('created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_cost_price) as total, COUNT(*) as n")
            ->groupBy('ym')->pluck('total', 'ym');
        $months = collect(range(0, 11))->map(fn ($i) => $from->copy()->addMonths($i))
            ->map(fn ($m) => ['label' => $m->format('M y'), 'total' => round((float) ($byMonth[$m->format('Y-m')] ?? 0), 2)]);

        return Inertia::render('Suppliers/Show', [
            'supplier' => $this->present($supplier) + [
                'address' => $supplier->address, 'product' => $supplier->product, 'comment' => $supplier->comment,
                'products_count' => Purchase::query()->where('supplier_id', $supplier->id)->distinct('product_id')->count('product_id'),
                'since' => $supplier->created_at?->format('d M Y'),
            ],
            'purchases' => $purchases,
            'filters' => ['q' => $q, 'per_page' => $perPage],
            'months' => $months,
            'currency' => currency_symbol(),
            'canManage' => $request->user()->can('core.catalogue.manage'),
            'canSeePurchases' => $request->user()->can('stock.purchases.view'),
        ]);
    }

    public function create()
    {
        return redirect()->route('suppliers.index', ['open' => 'create']);
    }

    public function edit($id)
    {
        return redirect()->route('suppliers.index', ['open' => (int) $id]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));

        return back()->with('success', "Supplier “{$supplier->name}” added.");
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->update($this->validated($request, $supplier->id));

        return back()->with('success', "Supplier “{$supplier->name}” saved.");
    }

    public function destroy(Request $request, $id = null)
    {
        $supplier = Supplier::findOrFail($id ?? $request->input('id'));
        $supplier->delete();

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['message' => 'Archived'])
            : back()->with('success', "Supplier “{$supplier->name}” archived. Past purchases keep the name; restore any time from Archived.");
    }

    public function restore($id)
    {
        $supplier = Supplier::onlyTrashed()->findOrFail($id);
        if (Supplier::query()->where('name', $supplier->name)->exists()) {
            return back()->with('error', "An active supplier named “{$supplier->name}” already exists, so this one can’t be restored.");
        }
        $supplier->restore();

        return back()->with('success', "Supplier “{$supplier->name}” restored.");
    }

    /** Select2 search for the classic purchase forms until they move to Inertia. */
    public function autocompleteName(Request $request)
    {
        $page = Supplier::query()->where('name', 'like', '%'.trim((string) $request->get('term', '')).'%')
            ->select('name')->distinct()->orderBy('name')->simplePaginate(10);

        return response()->json([
            'results' => collect($page->items())->map(fn ($r) => ['id' => $r->name, 'text' => $r->name])->values(),
            'pagination' => ['more' => $page->hasMorePages()],
        ]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('suppliers', 'name')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($ignoreId)],
            'company' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
            'product' => ['nullable', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.unique' => 'You already have a supplier with this name.',
            'phone.regex' => 'Use digits, spaces, +, - or brackets (6–30 characters).',
        ]);

        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
    }

    private function withTotals(Builder $query): Builder
    {
        $base = fn () => Purchase::query()->whereColumn('purchases.supplier_id', 'suppliers.id');

        return $query->select('suppliers.*')
            ->selectSub($base()->selectRaw('count(*)'), 'purchases_count')
            ->selectSub($base()->selectRaw('coalesce(sum(total_cost_price), 0)'), 'purchased_total')
            ->selectSub($base()->selectRaw('max(created_at)'), 'last_purchase_at');
    }

    private function present(Supplier $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'company' => $s->company,
            'phone' => $s->phone,
            'email' => $s->email,
            'purchases' => (int) $s->purchases_count,
            'purchased_total' => round((float) $s->purchased_total, 2),
            'last_purchase' => $s->last_purchase_at ? \Illuminate\Support\Carbon::parse($s->last_purchase_at)->format('d M Y') : null,
            'archived' => $s->trashed(),
            'address' => $s->address,
            'product' => $s->product,
            'comment' => $s->comment,
        ];
    }
}
