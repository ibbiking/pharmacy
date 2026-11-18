<?php

namespace App\Services\Accounting;

use App\Events;
use App\Models\Documents\Document;
use App\Models\Documents\Payment;
use App\Models\Expense\Expense;
use App\Models\Inventory\StockAdjustment;
use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Converting a business's past data into the books in the background while people keep working (phase 6,
 * docs/BUSINESS_TYPES_AND_SERVICES.md; the owner's "temporary transactions" idea made safe):
 *
 *  1. Switching Books on (or "Post history again") queues a run (books_syncs) and the BackfillAccountingForBusiness job.
 *  2. While a run is queued / running, a new sale, purchase, payment… is saved normally, but its journal is NOT posted
 *     straight away: a row in pending_postings remembers it (PostToLedger → defer()).
 *  3. The job posts the whole history (idempotent, every journal on the transaction's own date), then drains
 *     pending_postings in arrival order, again and again until none is left, and only then marks the run done.
 *  4. A scheduled sweep (accounting:drain-pending, every 5 minutes) drains rows left by a run that died and fails runs
 *     stuck for more than two hours, so nothing waits forever.
 * A per-business cache lock makes sure only one run or drain works on a business at a time. Re-posting a source replaces
 * its journal, so nothing is ever posted twice.
 */
class BooksSyncService
{
    public const STALE_MINUTES = 120;

    public function __construct(private readonly LedgerPoster $poster)
    {
    }

    /** Queue a run (unless one is already queued / running) and its job. */
    public function start(int $businessId, string $reason = 'switched_on'): int
    {
        $open = DB::table('books_syncs')->where('business_id', $businessId)->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '>', now()->subMinutes(self::STALE_MINUTES))->value('id');
        if ($open) {
            return (int) $open;
        }
        $id = DB::table('books_syncs')->insertGetId(['business_id' => $businessId, 'status' => 'queued', 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        \App\Jobs\BackfillAccountingForBusiness::dispatch($businessId, $id)->afterCommit();

        return $id;
    }

    /** A run is converting this business's history right now (new postings must wait). */
    public function isBusy(int $businessId): bool
    {
        return DB::table('books_syncs')->where('business_id', $businessId)->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '>', now()->subMinutes(self::STALE_MINUTES))->exists();
    }

    /** For screens: the open run, if any. */
    public function current(int $businessId): ?array
    {
        $run = DB::table('books_syncs')->where('business_id', $businessId)->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '>', now()->subMinutes(self::STALE_MINUTES))->latest('id')->first();

        return $run ? ['status' => $run->status, 'since' => $run->created_at, 'waiting' => DB::table('pending_postings')->where('business_id', $businessId)->count()] : null;
    }

    /**
     * PostToLedger: while a run is busy, remember the event instead of posting it. Returns true when deferred.
     */
    public function deferIfBusy(object $event): bool
    {
        $rows = $this->rowsFor($event);
        if (! $rows || ! $this->isBusy($rows[0]['business_id'])) {
            return false;
        }
        DB::table('pending_postings')->insert(array_map(fn ($r) => $r + ['created_at' => now()], $rows));

        return true;
    }

    /** The job: post the history, then everything that arrived meanwhile, then mark the run done. */
    public function run(int $businessId, ?int $runId = null): array
    {
        $runId ??= $this->start($businessId, 'manual');
        $lock = Cache::lock("books-sync:{$businessId}", 3600);
        if (! $lock->get()) {
            return []; // another run / drain is working on this business; it will drain the rest
        }
        try {
            DB::table('books_syncs')->where('id', $runId)->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
            $counts = $this->poster->backfill($businessId);
            $posted = $this->drainLocked($businessId, $runId);
            DB::table('books_syncs')->where('id', $runId)->update(['status' => 'done', 'finished_at' => now(), 'counts' => json_encode($counts),
                'pending_posted' => $posted, 'updated_at' => now()]);
            // Anything that slipped in between the last drain and "done" is posted now (nothing waits for the sweep).
            $this->drainLocked($businessId, null);

            return $counts;
        } catch (\Throwable $e) {
            DB::table('books_syncs')->where('id', $runId)->update(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /** Sweep: drain businesses with waiting postings and no live run; fail runs stuck for too long. */
    public function sweep(): int
    {
        DB::table('books_syncs')->whereIn('status', ['queued', 'running'])->where('updated_at', '<=', now()->subMinutes(self::STALE_MINUTES))
            ->update(['status' => 'failed', 'finished_at' => now(), 'error' => 'Stopped without finishing; the sweep posted what was waiting.', 'updated_at' => now()]);
        $total = 0;
        foreach (DB::table('pending_postings')->distinct()->pluck('business_id') as $businessId) {
            if ($this->isBusy((int) $businessId)) {
                continue;
            }
            $lock = Cache::lock("books-sync:{$businessId}", 600);
            if ($lock->get()) {
                try {
                    $total += $this->drainLocked((int) $businessId, null);
                } finally {
                    $lock->release();
                }
            }
        }

        return $total;
    }

    /** Post waiting rows in arrival order until none is left (rows added meanwhile are picked up by the next loop). */
    private function drainLocked(int $businessId, ?int $runId): int
    {
        $posted = 0;
        while ($rows = DB::table('pending_postings')->where('business_id', $businessId)->orderBy('id')->limit(200)->get()->all()) {
            foreach ($rows as $row) {
                $this->replay($row);
                DB::table('pending_postings')->where('id', $row->id)->delete();
                $posted++;
            }
            if ($runId) {
                DB::table('books_syncs')->where('id', $runId)->update(['updated_at' => now()]); // still alive
            }
        }

        return $posted;
    }

    private function replay(object $row): void
    {
        $find = fn (string $model) => $model::withoutGlobalScopes()->where('business_id', $row->business_id)->find($row->ref_id);
        match ($row->kind) {
            'sale' => ($i = $find(Invoice::class)) ? $this->poster->posSale($i) : null,
            'return' => ($i = $find(Invoice::class)) ? $this->poster->posReturn($i, (string) $row->extra) : null,
            'purchase' => ($p = $find(Purchase::class)) ? $this->poster->purchase($p, $row->extra ?: 'purchase') : null,
            'adjustment' => ($a = $find(StockAdjustment::class)) ? $this->poster->adjustment($a) : null,
            'document' => ($d = $find(Document::class)) ? $this->poster->document($d) : null,
            'payment' => ($p = $find(Payment::class)) ? $this->poster->payment($p) : null,
            'expense' => ($e = $find(Expense::class)) ? $this->poster->expense($e) : null,
            default => null,
        };
    }

    /** @return array<int, array{business_id: int, kind: string, ref_id: int, extra: ?string}> */
    private function rowsFor(object $event): array
    {
        $row = fn ($model, string $kind, ?string $extra = null) => ['business_id' => (int) $model->business_id, 'kind' => $kind, 'ref_id' => (int) $model->id, 'extra' => $extra];

        return match (true) {
            $event instanceof Events\SaleCompleted => [$row($event->invoice, 'sale')],
            $event instanceof Events\SaleReturned => [$row($event->invoice, 'return', $event->returnNo)],
            $event instanceof Events\GoodsReceived => $event->purchases->map(fn ($p) => $row($p, 'purchase', $event->source))->values()->all(),
            $event instanceof Events\PurchaseChanged => [$row($event->purchase, 'purchase')],
            $event instanceof Events\StockAdjusted => [$row($event->adjustment, 'adjustment')],
            $event instanceof Events\DocumentChanged => [$row($event->document, 'document')],
            $event instanceof Events\PaymentChanged => [$row($event->payment, 'payment')],
            $event instanceof Events\ExpenseChanged => [$row($event->expense, 'expense')],
            default => [],
        };
    }
}
