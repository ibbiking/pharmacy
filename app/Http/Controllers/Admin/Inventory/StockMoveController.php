<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockTransfer;
use App\Services\Stock\StockMovementService;
use App\Services\Stock\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Stock transfers and adjustments (Inventory Pro, Roadmap step 7): one list / form / detail screen for both kinds
 * (Pages/Inventory/Moves.tsx, MoveForm.tsx, MoveShow.tsx). Moves are final: a wrong move is undone with another move.
 */
class StockMoveController extends Controller
{
    public function __construct(private readonly StockMovementService $moves, private readonly WarehouseService $warehouses)
    {
    }

    private function kind(Request $request): string
    {
        return $request->routeIs('transfers.*') ? 'transfer' : 'adjustment';
    }

    public function index(Request $request)
    {
        $kind = $this->kind($request);
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $status = in_array($request->input('status'), ['increase', 'decrease', 'month'], true) ? $request->input('status') : null;

        if ($kind === 'transfer') {
            $rows = StockTransfer::query()->with(['from:id,name', 'to:id,name'])->withCount('lines')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('note', 'like', "%{$q}%")))
                ->when($status === 'month', fn ($w) => $w->where('date', '>=', now()->startOfMonth()))
                ->orderBy('date', $dir)->orderBy('id', $dir)->paginate($perPage)->withQueryString()
                ->through(fn (StockTransfer $t) => [
                    'id' => $t->id, 'number' => $t->number, 'date' => $t->date?->format('d M Y'), 'lines' => $t->lines_count,
                    'title' => "{$t->from?->name} → {$t->to?->name}", 'note' => $t->note, 'value' => null, 'direction' => null,
                ]);
            $stats = fn () => ['all' => StockTransfer::count(), 'month' => StockTransfer::where('date', '>=', now()->startOfMonth())->count()];
        } else {
            $rows = StockAdjustment::query()->with('warehouse:id,name')->withCount('lines')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('note', 'like', "%{$q}%")))
                ->when(in_array($status, ['increase', 'decrease'], true), fn ($w) => $w->where('direction', $status))
                ->when($status === 'month', fn ($w) => $w->where('date', '>=', now()->startOfMonth()))
                ->orderBy('date', $dir)->orderBy('id', $dir)->paginate($perPage)->withQueryString()
                ->through(fn (StockAdjustment $a) => [
                    'id' => $a->id, 'number' => $a->number, 'date' => $a->date?->format('d M Y'), 'lines' => $a->lines_count,
                    'title' => StockAdjustment::REASONS[$a->reason] ?? $a->reason, 'note' => trim(($a->warehouse?->name ? "{$a->warehouse->name} · " : '').($a->note ?? ''), ' ·') ?: null,
                    'value' => (float) $a->value, 'direction' => $a->direction,
                ]);
            $stats = fn () => [
                'all' => StockAdjustment::count(), 'month' => StockAdjustment::where('date', '>=', now()->startOfMonth())->count(),
                'increase' => StockAdjustment::where('direction', 'increase')->count(), 'decrease' => StockAdjustment::where('direction', 'decrease')->count(),
                'written_off' => round((float) StockAdjustment::where('direction', 'decrease')->where('date', '>=', now()->startOfMonth())->sum('value'), 2),
            ];
        }

        return Inertia::render('Inventory/Moves', [
            'kind' => $kind,
            'rows' => $rows,
            'filters' => ['q' => $q, 'sort' => 'date', 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => $stats,
            'canManage' => $request->user()->can('inventory_pro.manage'),
            'currency' => currency_symbol(),
        ]);
    }

    public function create(Request $request)
    {
        $kind = $this->kind($request);
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $default = $this->warehouses->defaultId($businessId);
        $defaultWarehouse = $default ? \App\Models\Inventory\Warehouse::find($default) : null;

        return Inertia::render('Inventory/MoveForm', [
            'kind' => $kind,
            'warehouse' => $defaultWarehouse ? ['value' => $defaultWarehouse->id, 'label' => $defaultWarehouse->name] : null,
            'reasons' => collect(StockAdjustment::REASONS)->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $kind = $this->kind($request);
        if ($kind === 'transfer') {
            $t = $this->moves->transfer($request->all(), $request->user()->id);

            session()->flash('drawer_follow', true); // inside the side drawer: show the result there

            return redirect()->route('transfers.show', $t->id)->with('success', "Transfer {$t->number} saved: the stock is in the new warehouse.");
        }
        $a = $this->moves->adjust($request->all(), $request->user()->id);

        session()->flash('drawer_follow', true); // inside the side drawer: show the result there

        return redirect()->route('adjustments.show', $a->id)->with('success', "Adjustment {$a->number} saved.");
    }

    public function show(Request $request, int $id)
    {
        $kind = $this->kind($request);
        $doc = $kind === 'transfer'
            ? StockTransfer::query()->with(['lines.product:id,product_name', 'lines.unit:id,name', 'from', 'to', 'creator:id,name'])->findOrFail($id)
            : StockAdjustment::query()->with(['lines.product:id,product_name', 'lines.unit:id,name', 'warehouse', 'creator:id,name'])->findOrFail($id);

        return Inertia::render('Inventory/MoveShow', [
            'kind' => $kind,
            'move' => [
                'id' => $doc->id, 'number' => $doc->number, 'date' => $doc->date?->format('d M Y'), 'note' => $doc->note, 'by' => $doc->creator?->name,
                'from' => $kind === 'transfer' ? $doc->from?->name : null, 'to' => $kind === 'transfer' ? $doc->to?->name : null,
                'warehouse' => $kind === 'adjustment' ? $doc->warehouse?->name : null,
                'direction' => $doc->direction ?? null, 'reason' => isset($doc->reason) ? (StockAdjustment::REASONS[$doc->reason] ?? $doc->reason) : null,
                'value' => (float) ($doc->value ?? 0),
                'lines' => $doc->lines->map(fn ($l) => ['product' => $l->product?->product_name, 'unit' => $l->unit?->name, 'qty' => (float) $l->qty, 'value' => (float) ($l->value ?? 0)]),
            ],
            'currency' => currency_symbol(),
        ]);
    }
}
