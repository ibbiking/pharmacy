<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\LedgerPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Journals, ledger, financial reports and the accounting setting (Roadmap step 10). */
class BooksController extends Controller
{
    public function __construct(private readonly AccountingService $books)
    {
    }

    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    private function range(Request $request): array
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    public function journals(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $source = in_array($request->input('status'), ['manual', 'pos_invoice', 'pos_return', 'purchase', 'document', 'payment', 'expense', 'stock_adjustment'], true) ? $request->input('status') : null;
        [$from, $to] = $this->range($request);
        $rows = JournalEntry::query()->whereBetween('date', [$from, $to])
            ->when($source, fn ($w) => $w->where('source_type', $source))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('memo', 'like', "%{$q}%")))
            ->orderByDesc('date')->orderByDesc('id')->paginate($perPage)->withQueryString()
            ->through(fn (JournalEntry $e) => ['id' => $e->id, 'number' => $e->number, 'date' => $e->date?->format('d M Y'), 'memo' => $e->memo,
                'source' => $e->source_type, 'amount' => $e->amount, 'manual' => $e->is_manual]);

        return Inertia::render('Accounting/Journals', [
            'rows' => $rows,
            'filters' => ['q' => $q, 'per_page' => $perPage, 'status' => $source, 'from' => $from, 'to' => $to],
            'stats' => fn () => JournalEntry::query()->whereBetween('date', [$from, $to])->groupBy('source_type')->selectRaw('source_type, count(*) as n')->pluck('n', 'source_type'),
            'canManage' => $request->user()->can('accounting.manage'),
            'purchasesOnCredit' => app(LedgerPoster::class)->purchasesOnCredit($this->businessId()),
            'currency' => currency_symbol(),
        ]);
    }

    public function journal(int $id)
    {
        $e = JournalEntry::query()->with(['lines.account', 'creator:id,name'])->findOrFail($id);

        return Inertia::render('Accounting/JournalShow', [
            'entry' => ['id' => $e->id, 'number' => $e->number, 'date' => $e->date?->format('d M Y'), 'memo' => $e->memo, 'source' => $e->source_type, 'manual' => $e->is_manual,
                'by' => $e->creator?->name, 'amount' => $e->amount,
                'lines' => $e->lines->map(fn ($l) => ['account' => $l->account?->code.' · '.$l->account?->name, 'account_id' => $l->account_id, 'debit' => $l->debit, 'credit' => $l->credit, 'description' => $l->description])],
            'currency' => currency_symbol(),
        ]);
    }

    public function createJournal()
    {
        return Inertia::render('Accounting/JournalForm', ['today' => today()->toDateString(), 'currency' => currency_symbol()]);
    }

    public function storeJournal(Request $request)
    {
        $entry = $this->books->manual($this->businessId(), $request->all(), $request->user()->id);

        return redirect()->route('accounting.journals.show', $entry->id)->with('success', "Journal {$entry->number} posted.");
    }

    public function ledger(Request $request, int $id)
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('Accounting/Ledger', $this->books->ledger($this->businessId(), $id, $from, $to, 50, max(1, $request->integer('page', 1))) + [
            'filters' => ['from' => $from, 'to' => $to], 'currency' => currency_symbol(),
        ]);
    }

    public function reports(Request $request)
    {
        [$from, $to] = $this->range($request);
        $b = $this->businessId();
        $report = in_array($request->input('report'), ['pl', 'bs', 'tb', 'tax'], true) ? $request->input('report') : 'pl';

        return Inertia::render('Accounting/Reports', [
            'report' => $report,
            'filters' => ['from' => $from, 'to' => $to, 'report' => $report],
            'data' => match ($report) {
                'pl' => $this->books->profitAndLoss($b, $from, $to),
                'bs' => $this->books->balanceSheet($b, $to),
                'tb' => $this->books->trialBalance($b, $to),
                'tax' => $this->books->taxSummary($b, $from, $to),
            },
            'currency' => currency_symbol(),
        ]);
    }

    /** Setting: plain purchases are paid at once (Cash) or owed to the supplier (A/P). Optional repost of history. */
    public function settings(Request $request)
    {
        $data = $request->validate(['purchases_on_credit' => ['required', 'boolean']]);
        $b = $this->businessId();
        $q = DB::table('preferences')->where('business_id', $b)->where('type', 'accounting')->where('slug', 'purchases-on-credit')->whereNull('deleted_at');
        $q->exists()
            ? $q->update(['status' => $data['purchases_on_credit'], 'updated_at' => now()])
            : DB::table('preferences')->insert(['business_id' => $b, 'type' => 'accounting', 'slug' => 'purchases-on-credit', 'preference' => 'Purchases are on credit (A/P)',
                'status' => $data['purchases_on_credit'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', $data['purchases_on_credit'] ? 'New purchases are now owed to the supplier (Accounts payable).' : 'New purchases are now paid in cash.');
    }

    public function backfill(Request $request)
    {
        \App\Jobs\BackfillAccountingForBusiness::dispatch($this->businessId());

        return back()->with('success', 'Re-posting the history to the books. It finishes in the background.');
    }
}
