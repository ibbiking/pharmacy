<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Services\Modules\ModuleManager;
use Database\Seeders\DefaultChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The books (Roadmap step 10): balanced double-entry journals and the reports built on them.
 * Automatic journals are keyed by (source_type, source_id, source_event): posting again replaces the previous entry,
 * reversing archives it, so re-running a backfill never doubles anything.
 * All queries name the business explicitly (they also run from queued jobs without a session).
 */
class AccountingService
{
    private array $accountIds = [];

    public function __construct(private readonly ModuleManager $modules)
    {
    }

    public function enabled(?int $businessId): bool
    {
        return $businessId !== null && $this->modules->isActive($businessId, 'accounting');
    }

    public function ensureChart(int $businessId): void
    {
        (new DefaultChartOfAccountsSeeder)->forBusiness($businessId);
        unset($this->accountIds[$businessId]);
    }

    /** Id of a system account (cash, ar, inventory …); seeds the chart when it is missing. */
    public function accountId(int $businessId, string $key): int
    {
        if (! isset($this->accountIds[$businessId][$key])) {
            $id = DB::table('accounts')->where('business_id', $businessId)->where('system_key', $key)->whereNull('deleted_at')->value('id');
            if (! $id) {
                $this->ensureChart($businessId);
                $id = DB::table('accounts')->where('business_id', $businessId)->where('system_key', $key)->whereNull('deleted_at')->value('id');
            }
            if (! $id) {
                throw new RuntimeException("System account [{$key}] is missing for business {$businessId}.");
            }
            $this->accountIds[$businessId][$key] = (int) $id;
        }

        return $this->accountIds[$businessId][$key];
    }

    /**
     * Post (or re-post) the journal of one source event.
     *
     * @param  array<int, array{account: string|int, debit?: float, credit?: float, description?: string|null, contact?: array{0: string, 1: int}|null}>  $lines
     *         account: a system key ('cash') or an account id
     */
    public function post(int $businessId, string $sourceType, int $sourceId, string $event, $date, string $memo, ?string $number, array $lines, ?int $userId = null): ?JournalEntry
    {
        $rows = [];
        foreach ($lines as $l) {
            $debit = round((float) ($l['debit'] ?? 0), 2);
            $credit = round((float) ($l['credit'] ?? 0), 2);
            // Negative amounts flip to the other side (e.g. a favourable price variance).
            if ($debit < 0) { $credit += -$debit; $debit = 0.0; }
            if ($credit < 0) { $debit += -$credit; $credit = 0.0; }
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $rows[] = [
                'account_id' => is_int($l['account']) ? $l['account'] : $this->accountId($businessId, $l['account']),
                'debit' => $debit, 'credit' => $credit, 'description' => $l['description'] ?? null,
                'contact_type' => $l['contact'][0] ?? null, 'contact_id' => $l['contact'][1] ?? null,
            ];
        }
        $dr = round(array_sum(array_column($rows, 'debit')), 2);
        $cr = round(array_sum(array_column($rows, 'credit')), 2);
        if (abs($dr - $cr) > 0.05) {
            throw new RuntimeException("Unbalanced journal for {$sourceType} #{$sourceId} ({$event}): {$dr} ≠ {$cr}.");
        }
        if (abs($dr - $cr) >= 0.01 && $rows) {
            // Rounding cents from proportional splits go to the largest line.
            usort($rows, fn ($a, $b) => max($b['debit'], $b['credit']) <=> max($a['debit'], $a['credit']));
            $rows[0][$rows[0]['debit'] > 0 ? 'debit' : 'credit'] += $rows[0]['debit'] > 0 ? round($cr - $dr, 2) : round($dr - $cr, 2);
        }

        return DB::transaction(function () use ($businessId, $sourceType, $sourceId, $event, $date, $memo, $number, $rows, $userId, $dr) {
            $this->reverse($businessId, $sourceType, $sourceId, $event);
            if (! $rows) {
                return null;
            }
            $entry = new JournalEntry([
                'number' => $number, 'date' => $date, 'memo' => mb_substr($memo, 0, 500), 'source_type' => $sourceType, 'source_id' => $sourceId,
                'source_event' => $event, 'is_manual' => false, 'amount' => $dr, 'created_by' => $userId,
            ]);
            $entry->business_id = $businessId;
            $entry->save();
            $day = \Carbon\Carbon::parse($date)->toDateString();
            foreach ($rows as $r) {
                $line = new JournalLine($r + ['journal_entry_id' => $entry->id, 'date' => $day]);
                $line->business_id = $businessId;
                $line->save();
            }

            return $entry;
        });
    }

    /** Archive the journal(s) of a source (one event, or all of them). */
    public function reverse(int $businessId, string $sourceType, int $sourceId, ?string $event = null): void
    {
        $ids = DB::table('journal_entries')->where('business_id', $businessId)->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->when($event, fn ($q) => $q->where('source_event', $event))->whereNull('deleted_at')->pluck('id');
        if ($ids->isNotEmpty()) {
            $now = now();
            DB::table('journal_lines')->whereIn('journal_entry_id', $ids)->update(['deleted_at' => $now]);
            DB::table('journal_entries')->whereIn('id', $ids)->update(['deleted_at' => $now]);
        }
    }

    /** A manual journal: balanced, at least two lines. */
    public function manual(int $businessId, array $input, ?int $userId): JournalEntry
    {
        $data = validator($input, [
            'date' => ['required', 'date'], 'memo' => ['required', 'string', 'max:500'], 'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.account_id' => ['required', 'integer'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ], ['lines.min' => 'A journal needs at least two lines.'])->validate();
        $accounts = Account::query()->whereIn('id', collect($data['lines'])->pluck('account_id'))->pluck('id')->all();
        $lines = [];
        foreach ($data['lines'] as $i => $l) {
            if (! in_array((int) $l['account_id'], $accounts, true)) {
                throw ValidationException::withMessages(["lines.{$i}.account_id" => 'Pick an account from your chart.']);
            }
            $d = round((float) ($l['debit'] ?? 0), 2);
            $c = round((float) ($l['credit'] ?? 0), 2);
            if (($d > 0) === ($c > 0)) {
                throw ValidationException::withMessages(["lines.{$i}.debit" => 'Enter a debit or a credit on each line (not both).']);
            }
            $lines[] = ['account_id' => (int) $l['account_id'], 'debit' => $d, 'credit' => $c, 'description' => $l['description'] ?? null];
        }
        $dr = round(array_sum(array_column($lines, 'debit')), 2);
        $cr = round(array_sum(array_column($lines, 'credit')), 2);
        if ($dr !== $cr) {
            throw ValidationException::withMessages(['lines' => sprintf('Debits (%.2f) and credits (%.2f) must be equal.', $dr, $cr)]);
        }

        return DB::transaction(function () use ($businessId, $data, $lines, $userId, $dr) {
            $entry = new JournalEntry([
                'number' => app(\App\Services\Numbering\NumberSeriesService::class)->next($businessId, 'journal'), 'date' => $data['date'], 'memo' => $data['memo'],
                'source_type' => 'manual', 'is_manual' => true, 'amount' => $dr, 'created_by' => $userId,
            ]);
            $entry->business_id = $businessId;
            $entry->save();
            foreach ($lines as $l) {
                $line = new JournalLine($l + ['journal_entry_id' => $entry->id, 'date' => $data['date']]);
                $line->business_id = $businessId;
                $line->save();
            }

            return $entry;
        });
    }

    // ============================================================================================ reports

    /** Balance per account for a period (or up to a date when $from is null). */
    public function balances(int $businessId, ?string $from, string $to): \Illuminate\Support\Collection
    {
        return DB::table('accounts as a')
            ->leftJoin('journal_lines as l', fn ($j) => $j->on('l.account_id', '=', 'a.id')->whereNull('l.deleted_at')->where('l.business_id', $businessId)
                ->when($from, fn ($w) => $w->where('l.date', '>=', $from))->where('l.date', '<=', $to))
            ->where('a.business_id', $businessId)->whereNull('a.deleted_at')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.sub_type', 'a.system_key')
            ->orderBy('a.code')
            ->selectRaw('a.id, a.code, a.name, a.type, a.sub_type, a.system_key, coalesce(sum(l.debit), 0) as debit, coalesce(sum(l.credit), 0) as credit')
            ->get()
            ->map(function ($a) {
                $a->debit = round((float) $a->debit, 2);
                $a->credit = round((float) $a->credit, 2);
                $a->balance = in_array($a->type, ['asset', 'expense'], true) ? round($a->debit - $a->credit, 2) : round($a->credit - $a->debit, 2);

                return $a;
            });
    }

    public function trialBalance(int $businessId, string $to): array
    {
        $rows = $this->balances($businessId, null, $to)->filter(fn ($a) => $a->debit != 0 || $a->credit != 0)->map(fn ($a) => [
            'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type,
            'debit' => max(0, round($a->debit - $a->credit, 2)), 'credit' => max(0, round($a->credit - $a->debit, 2)),
        ])->values();

        return ['rows' => $rows, 'debit' => round($rows->sum('debit'), 2), 'credit' => round($rows->sum('credit'), 2)];
    }

    public function profitAndLoss(int $businessId, string $from, string $to): array
    {
        $b = $this->balances($businessId, $from, $to);
        $section = fn (array $subs) => $b->whereIn('sub_type', $subs)->filter(fn ($a) => $a->balance != 0)->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'amount' => $a->balance])->values();
        $income = $section(['income']);
        $cos = $section(['cost_of_sales']);
        $expenses = $section(['expense']);
        $otherIncome = $section(['other_income']);
        $otherExpenses = $section(['other_expense']);
        $gross = round($income->sum('amount') - $cos->sum('amount'), 2);
        $operating = round($gross - $expenses->sum('amount'), 2);
        $net = round($operating + $otherIncome->sum('amount') - $otherExpenses->sum('amount'), 2);

        return compact('income', 'cos', 'expenses', 'otherIncome', 'otherExpenses', 'gross', 'operating', 'net') + [
            'income_total' => round($income->sum('amount'), 2), 'cos_total' => round($cos->sum('amount'), 2), 'expenses_total' => round($expenses->sum('amount'), 2),
        ];
    }

    public function balanceSheet(int $businessId, string $asOf): array
    {
        $b = $this->balances($businessId, null, $asOf);
        $pick = fn (string $type) => $b->where('type', $type)->filter(fn ($a) => $a->balance != 0)->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'sub_type' => $a->sub_type, 'amount' => $a->balance])->values();
        $assets = $pick('asset');
        $liabilities = $pick('liability');
        $equity = $pick('equity');
        // Profit not yet closed into retained earnings shows as "Current year earnings".
        $earnings = round($b->where('type', 'income')->sum('balance') - $b->where('type', 'expense')->sum('balance'), 2);
        if ($earnings != 0) {
            $equity->push(['id' => null, 'code' => '', 'name' => 'Current earnings (profit to date)', 'sub_type' => 'equity', 'amount' => $earnings]);
        }

        return [
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity,
            'assets_total' => round($assets->sum('amount'), 2), 'liabilities_total' => round($liabilities->sum('amount'), 2), 'equity_total' => round($equity->sum('amount'), 2),
        ];
    }

    /** Ledger lines of one account with a running balance (server-paged). */
    public function ledger(int $businessId, int $accountId, string $from, string $to, int $perPage = 50, int $page = 1): array
    {
        $account = Account::withoutGlobalScopes()->where('business_id', $businessId)->findOrFail($accountId);
        $sign = $account->debitNormal() ? 1 : -1;
        $opening = (float) DB::table('journal_lines')->where('business_id', $businessId)->where('account_id', $accountId)->whereNull('deleted_at')
            ->where('date', '<', $from)->selectRaw('coalesce(sum(debit - credit), 0) as v')->value('v') * $sign;
        $query = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.business_id', $businessId)->where('l.account_id', $accountId)->whereNull('l.deleted_at')
            ->whereBetween('l.date', [$from, $to])->orderBy('l.date')->orderBy('l.id')
            ->select('l.id', 'l.date', 'l.debit', 'l.credit', 'l.description', 'e.number', 'e.memo', 'e.source_type', 'e.source_id', 'e.id as entry_id');
        // Running balance: opening + everything before this page (computed before paginate() adds its own limit).
        $page = max(1, $page);
        $before = $page > 1 ? (clone $query)->limit(($page - 1) * $perPage)->get()->sum(fn ($r) => ((float) $r->debit - (float) $r->credit) * $sign) : 0.0;
        $paged = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        $running = $opening + $before;
        $rows = collect($paged->items())->map(function ($r) use (&$running, $sign) {
            $running += ((float) $r->debit - (float) $r->credit) * $sign;

            return ['id' => $r->id, 'date' => \Carbon\Carbon::parse($r->date)->format('d M Y'), 'number' => $r->number, 'memo' => $r->description ?: $r->memo,
                'debit' => (float) $r->debit, 'credit' => (float) $r->credit, 'balance' => round($running, 2), 'source' => $r->source_type, 'entry_id' => $r->entry_id];
        });
        $closing = $opening + (float) DB::table('journal_lines')->where('business_id', $businessId)->where('account_id', $accountId)->whereNull('deleted_at')
            ->whereBetween('date', [$from, $to])->selectRaw('coalesce(sum(debit - credit), 0) as v')->value('v') * $sign;

        return ['account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'opening' => round($opening, 2), 'closing' => round($closing, 2), 'rows' => $paged->setCollection($rows)];
    }

    /** Sales tax collected vs. paid for a period. */
    public function taxSummary(int $businessId, string $from, string $to): array
    {
        $sum = fn (string $key) => DB::table('journal_lines')->where('business_id', $businessId)->where('account_id', $this->accountId($businessId, $key))
            ->whereNull('deleted_at')->whereBetween('date', [$from, $to])->selectRaw('coalesce(sum(credit), 0) as c, coalesce(sum(debit), 0) as d')->first();
        $out = $sum('tax_payable');
        $in = $sum('input_tax');
        $collected = round((float) $out->c - (float) $out->d, 2);
        $paid = round((float) $in->d - (float) $in->c, 2);

        return ['collected' => $collected, 'paid' => $paid, 'net' => round($collected - $paid, 2)];
    }
}
