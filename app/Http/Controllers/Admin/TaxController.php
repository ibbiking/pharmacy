<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Taxes: the Inertia pilot module (Roadmap step 3). Reference pattern for converting the other
 * master-data screens: server-paginated list + modal create/edit + soft delete.
 */
class TaxController extends Controller
{
    private const SORTABLE = ['name', 'rate', 'created_at', 'usage'];
    private const PER_PAGE = [10, 20, 50];

    public function index(Request $request)
    {
        $filters = $this->filters($request);

        $usage = fn ($table) => DB::table($table)->selectRaw('count(*)')->whereColumn("{$table}.tax_id", 'taxes.id');

        $taxes = Tax::query()
            ->select('taxes.*')
            ->selectSub($usage('purchase_taxes'), 'purchase_usage')
            ->selectSub($usage('sale_taxes'), 'sale_usage')
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', '%'.$filters['q'].'%'))
            ->when($filters['sort'] === 'usage',
                fn ($q) => $q->orderByRaw('(purchase_usage + sale_usage) '.$filters['dir']),
                fn ($q) => $q->orderBy($filters['sort'], $filters['dir']))
            ->orderBy('id')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Tax $tax) => [
                'id' => $tax->id,
                'name' => $tax->name,
                'rate' => (float) $tax->rate,
                'usage' => (int) $tax->purchase_usage + (int) $tax->sale_usage,
                'created_at' => optional($tax->created_at)->format('d M Y'),
            ]);

        return Inertia::render('Taxes/Index', [
            'taxes' => $taxes,
            'filters' => $filters,
            'stats' => fn () => [
                'count' => Tax::count(),
                'average_rate' => round((float) Tax::avg('rate'), 2),
                'highest_rate' => (float) Tax::max('rate'),
                'in_use' => Tax::query()->where(fn ($q) => $q
                    ->whereExists(fn ($e) => $e->from('purchase_taxes')->whereColumn('purchase_taxes.tax_id', 'taxes.id'))
                    ->orWhereExists(fn ($e) => $e->from('sale_taxes')->whereColumn('sale_taxes.tax_id', 'taxes.id')))
                    ->count(),
            ],
            'open' => $request->boolean('create') ? 'create' : ($request->integer('edit') ?: null),
        ]);
    }

    /** Kept for old links: opens the list with the "add" form ready. */
    public function create()
    {
        return redirect()->route('taxes.index', ['create' => 1]);
    }

    /** Kept for old links: opens the list with this tax's edit form ready. */
    public function edit(Tax $tax)
    {
        return redirect()->route('taxes.index', ['edit' => $tax->id]);
    }

    public function store(Request $request)
    {
        Tax::create($this->validated($request));

        return back()->with('success', 'Tax added.');
    }

    public function update(Request $request, Tax $tax)
    {
        $tax->update($this->validated($request, $tax));

        return back()->with('success', 'Tax updated.');
    }

    /** Soft delete: purchases that already carry this tax keep it; it just can't be picked any more. */
    public function destroy(Tax $tax)
    {
        $tax->delete();

        return back()->with('success', "Tax “{$tax->name}” archived.");
    }

    private function validated(Request $request, ?Tax $tax = null): array
    {
        $businessId = session('impersonate_business_id') ?? session('business_id');

        return $request->validate([
            // Unique within this business only (was global across all businesses), ignoring archived taxes.
            'name' => ['required', 'string', 'max:100', Rule::unique('taxes', 'name')
                ->where('business_id', $businessId)
                ->whereNull('deleted_at')
                ->ignore($tax?->id)],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function filters(Request $request): array
    {
        $sort = in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'name';
        $perPage = in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20;

        return [
            'q' => trim((string) $request->input('q', '')),
            'sort' => $sort,
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => $perPage,
        ];
    }
}
