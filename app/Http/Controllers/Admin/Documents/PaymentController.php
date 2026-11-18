<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Documents\Document;
use App\Models\Documents\Payment;
use App\Models\Supplier;
use App\Services\Documents\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Payments received (selling.payments.*) and bill payments (buying.payments.*), Roadmap 8–9; customer / supplier
 * statements and A/R / A/P ageing.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    private function in(Request $request): bool
    {
        return str_starts_with((string) $request->route()?->getName(), 'selling.');
    }

    private function permission(bool $in, bool $manage = false): string
    {
        // Customer / Supplier credit micro services (every business type), not the B2B document modules.
        return $in ? ($manage ? 'customer_credit.payments' : 'customer_credit.view') : ($manage ? 'supplier_credit.payments' : 'supplier_credit.view');
    }

    public function index(Request $request)
    {
        $in = $this->in($request);
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $status = in_array($request->input('status'), ['month', 'unapplied'], true) ? $request->input('status') : null;
        $base = fn () => Payment::query()->where('direction', $in ? 'in' : 'out');

        $rows = $base()->with($in ? 'customer:id,name' : 'supplier:id,name')->withCount('allocations')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%")
                ->orWhereHas($in ? 'customer' : 'supplier', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($status === 'month', fn ($w) => $w->where('date', '>=', now()->startOfMonth()))
            ->when($status === 'unapplied', fn ($w) => $w->where('unapplied', '>', 0))
            ->orderBy('date', $dir)->orderBy('id', $dir)->paginate($perPage)->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id, 'number' => $p->number, 'party' => ($in ? $p->customer : $p->supplier)?->name, 'date' => $p->date?->format('d M Y'),
                'amount' => $p->amount, 'unapplied' => $p->unapplied, 'method' => Payment::METHODS[$p->method] ?? $p->method, 'reference' => $p->reference,
                'documents' => $p->allocations_count,
            ]);

        return Inertia::render('Payments/Index', [
            'direction' => $in ? 'in' : 'out',
            'rows' => $rows,
            'filters' => ['q' => $q, 'sort' => 'date', 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => [
                'all' => $base()->count(), 'month' => $base()->where('date', '>=', now()->startOfMonth())->count(),
                'month_amount' => round((float) $base()->where('date', '>=', now()->startOfMonth())->sum('amount'), 2),
                'unapplied' => $base()->where('unapplied', '>', 0)->count(), 'unapplied_amount' => round((float) $base()->sum('unapplied'), 2),
            ],
            'canCreate' => $request->user()->can($this->permission($in, true)),
            'currency' => currency_symbol(),
        ]);
    }

    public function create(Request $request)
    {
        $in = $this->in($request);
        abort_unless($request->user()->can($this->permission($in, true)), 403);
        $party = null;
        $doc = $request->integer('document_id') ? Document::query()->find($request->integer('document_id')) : null;
        $partyId = $doc ? ($in ? $doc->customer_id : $doc->supplier_id) : $request->integer($in ? 'customer_id' : 'supplier_id');
        if ($partyId) {
            $p = ($in ? Customer::class : Supplier::class)::find($partyId);
            $party = $p ? ['value' => $p->id, 'label' => $p->name] : null;
        }

        return Inertia::render('Payments/Form', [
            'direction' => $in ? 'in' : 'out',
            'party' => $party,
            'preselect' => $doc ? ['id' => $doc->id, 'amount' => $doc->balance] : null,
            'methods' => collect(Payment::METHODS)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'today' => today()->toDateString(),
            'currency' => currency_symbol(),
        ]);
    }

    /** Open invoices (in) or bills (out) of a party, for the allocation table (paged, oldest due first). */
    public function openDocuments(Request $request)
    {
        $in = $this->in($request);
        abort_unless($request->user()->can($this->permission($in, true)), 403);
        $partyId = $request->integer('party_id');
        $page = Document::query()->where('type', $in ? 'sales_invoice' : 'bill')->where($in ? 'customer_id' : 'supplier_id', $partyId)
            ->whereIn('status', ['unpaid', 'partial'])->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Document $d) => ['id' => $d->id, 'number' => $d->number, 'date' => $d->date?->format('d M Y'),
                'due_date' => $d->due_date?->format('d M Y'), 'overdue' => $d->isOverdue(), 'total' => $d->total, 'balance' => $d->balance]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'open_total' => round((float) Document::query()->where('type', $in ? 'sales_invoice' : 'bill')->where($in ? 'customer_id' : 'supplier_id', $partyId)
                ->whereIn('status', ['unpaid', 'partial'])->sum('balance'), 2),
        ]);
    }

    public function store(Request $request)
    {
        $in = $this->in($request);
        abort_unless($request->user()->can($this->permission($in, true)), 403);
        $payment = $this->documents->recordPayment($in ? 'in' : 'out', $request->all(), $request->user());

        session()->flash('drawer_follow', true); // inside the side drawer: show the result there

        return redirect()->route(($in ? 'selling' : 'buying').'.payments.show', $payment->id)
            ->with('success', ($in ? 'Payment received' : 'Bill payment').' '.$payment->number.' recorded.');
    }

    public function show(Request $request, int $id)
    {
        $in = $this->in($request);
        $p = Payment::query()->where('direction', $in ? 'in' : 'out')->with(['allocations.document', 'customer', 'supplier', 'creator:id,name'])->findOrFail($id);

        return Inertia::render('Payments/Show', [
            'direction' => $in ? 'in' : 'out',
            'payment' => [
                'id' => $p->id, 'number' => $p->number, 'date' => $p->date?->format('d M Y'), 'amount' => $p->amount, 'unapplied' => $p->unapplied,
                'method' => Payment::METHODS[$p->method] ?? $p->method, 'reference' => $p->reference, 'notes' => $p->notes, 'by' => $p->creator?->name,
                'party' => ($in ? $p->customer : $p->supplier)?->only(['id', 'name', 'company']),
                'allocations' => $p->allocations->map(fn ($a) => ['document_id' => $a->document_id, 'number' => $a->document?->number, 'amount' => $a->amount, 'balance' => $a->document?->balance, 'href' => $a->document?->href()]),
            ],
            'canDelete' => $request->user()->can($this->permission($in, true)),
            'currency' => currency_symbol(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $in = $this->in($request);
        abort_unless($request->user()->can($this->permission($in, true)), 403);
        $p = Payment::query()->where('direction', $in ? 'in' : 'out')->findOrFail($id);
        $this->documents->voidPayment($p);

        return redirect()->route(($in ? 'selling' : 'buying').'.payments.index')->with('success', "Payment {$p->number} deleted: what it paid is open again.");
    }

    // ------------------------------------------------------------------------------------------------ statements & ageing

    /** Customer / supplier statement (printable): opening balance, documents and payments with a running balance. */
    public function statement(Request $request, int $id)
    {
        $customer = $request->routeIs('customers.statement');
        $party = $customer ? Customer::query()->findOrFail($id) : Supplier::query()->findOrFail($id);
        $from = $request->date('from')?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();
        $key = $customer ? 'customer_id' : 'supplier_id';

        $docs = Document::query()->where($key, $id)->whereIn('type', $customer ? ['sales_invoice', 'credit_note'] : ['bill', 'vendor_credit'])
            ->whereNotIn('status', ['draft', 'void'])->get()->map(fn ($d) => [
                'date' => $d->date, 'ref' => $d->number, 'what' => $d->typeLabel(),
                'amount' => in_array($d->type, ['sales_invoice', 'bill'], true) ? $d->total : -$d->total,
            ]);
        $pays = Payment::query()->where($key, $id)->get()->map(fn ($p) => ['date' => $p->date, 'ref' => $p->number, 'what' => 'Payment ('.(Payment::METHODS[$p->method] ?? $p->method).')', 'amount' => -$p->amount]);
        $all = $docs->concat($pays)->sortBy(fn ($r) => $r['date']->format('Y-m-d').$r['ref'])->values();
        $opening = (float) $party->opening_balance + $all->filter(fn ($r) => $r['date']->toDateString() < $from)->sum('amount');
        $running = $opening;
        $rows = $all->filter(fn ($r) => $r['date']->toDateString() >= $from && $r['date']->toDateString() <= $to)->map(function ($r) use (&$running) {
            $running += $r['amount'];

            return $r + ['balance' => round($running, 2)];
        })->values();

        return view('documents.statement', ['party' => $party, 'customer' => $customer, 'from' => $from, 'to' => $to, 'opening' => round($opening, 2),
            'rows' => $rows, 'closing' => round($running, 2), 'business' => \App\Models\Business::find(session('impersonate_business_id') ?? session('business_id')),
            'currency' => currency_symbol()]);
    }

    /** A/R (reports.ar_aging) or A/P (reports.ap_aging) ageing: open balance per party in 0–30 / 31–60 / 61–90 / 90+ days overdue. */
    public function aging(Request $request)
    {
        $ar = $request->routeIs('reports.ar_aging');
        $key = $ar ? 'customer_id' : 'supplier_id';
        $table = $ar ? 'customers' : 'suppliers';
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $q = trim((string) $request->input('q', ''));
        $days = 'datediff(curdate(), coalesce(d.due_date, d.date))';
        $bucket = fn (string $cond) => "sum(case when {$cond} then d.balance else 0 end)";

        $query = DB::table('documents as d')->join("{$table} as p", 'p.id', '=', "d.{$key}")
            ->where('d.business_id', $businessId)->whereNull('d.deleted_at')->where('d.type', $ar ? 'sales_invoice' : 'bill')->whereIn('d.status', ['unpaid', 'partial'])
            ->when($q !== '', fn ($w) => $w->where('p.name', 'like', "%{$q}%"))
            ->groupBy("d.{$key}", 'p.name')
            ->selectRaw("d.{$key} as party_id, p.name, sum(d.balance) as total, {$bucket("{$days} <= 0")} as current_amount, {$bucket("{$days} between 1 and 30")} as d30,
                {$bucket("{$days} between 31 and 60")} as d60, {$bucket("{$days} between 61 and 90")} as d90, {$bucket("{$days} > 90")} as d90plus, count(*) as documents");
        $totals = DB::query()->fromSub(clone $query, 'x')->selectRaw('sum(total) as total, sum(current_amount) as current_amount, sum(d30) as d30, sum(d60) as d60, sum(d90) as d90, sum(d90plus) as d90plus')->first();
        $rows = $query->orderByDesc('total')->paginate($perPage)->withQueryString()->through(fn ($r) => [
            'id' => (int) $r->party_id, 'name' => $r->name, 'documents' => (int) $r->documents, 'total' => round((float) $r->total, 2), 'current' => round((float) $r->current_amount, 2),
            'd30' => round((float) $r->d30, 2), 'd60' => round((float) $r->d60, 2), 'd90' => round((float) $r->d90, 2), 'd90plus' => round((float) $r->d90plus, 2),
        ]);

        return Inertia::render('Reports/Aging', [
            'kind' => $ar ? 'ar' : 'ap',
            'rows' => $rows,
            'totals' => collect((array) $totals)->map(fn ($v) => round((float) $v, 2)),
            'filters' => ['q' => $q, 'per_page' => $perPage],
            'currency' => currency_symbol(),
        ]);
    }
}
