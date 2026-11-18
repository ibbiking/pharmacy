<?php

namespace Tests\Feature\Modules;

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalLine;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\BooksSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SalesScenario;
use Tests\TestCase;

/**
 * Books sync (phase 6, docs/BUSINESS_TYPES_AND_SERVICES.md): while the history is being converted, new postings wait in
 * pending_postings and are posted in order afterwards; a sweep finishes what a dead run left; nothing posts twice.
 */
class BooksSyncTest extends TestCase
{
    use RefreshDatabase;
    use SalesScenario;

    private $product;
    private array $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpBusiness('Sync Pharmacy');
        app(AccountingService::class)->ensureChart($this->business->id);
        [$this->product, $this->unit] = $this->makeProduct('Panadol', ['Box' => 1, 'Tablet' => 10], ['Tablet' => [10, 6], 'Box' => [100, 60]]);
        $this->purchase($this->product, $this->unit['Box'], 10, 60, 100, now()->addYear()->toDateString());
    }

    private function sell(): string
    {
        return $this->saveInvoice([$this->line($this->product, $this->unit['Box'], 1, 100)])->assertOk()->json('invoice_no');
    }

    private function busy(int $minutesAgo = 0): int
    {
        return DB::table('books_syncs')->insertGetId(['business_id' => $this->business->id, 'status' => 'running', 'reason' => 'manual',
            'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo)]);
    }

    private function saleJournals(): int
    {
        return JournalEntry::where('source_type', 'pos_invoice')->count();
    }

    private function assertBalanced(): void
    {
        $this->assertSame(round((float) JournalLine::sum('debit'), 2), round((float) JournalLine::sum('credit'), 2));
    }

    public function test_switching_books_on_converts_the_history_in_a_run(): void
    {
        $this->delete(route('modules.deactivate', 'accounting'))->assertSessionHas('success');
        $this->sell();
        $this->assertSame(0, $this->saleJournals(), 'Books off: nothing posted');

        $this->post(route('modules.activate', 'accounting'))->assertSessionHas('success');

        $run = DB::table('books_syncs')->where('business_id', $this->business->id)->latest('id')->first();
        $this->assertSame('done', $run->status);
        $this->assertSame('switched_on', $run->reason);
        $this->assertSame(1, $this->saleJournals());
        $this->assertBalanced();
    }

    public function test_new_work_waits_while_converting_and_is_posted_in_order_afterwards(): void
    {
        $runId = $this->busy();
        $first = $this->sell();
        $second = $this->sell();

        $this->assertSame(0, $this->saleJournals(), 'nothing posted while the run is busy');
        $waiting = DB::table('pending_postings')->where('business_id', $this->business->id)->orderBy('id')->get();
        $this->assertSame(['sale', 'sale'], $waiting->pluck('kind')->all());
        // The screens tell people what is going on.
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('booksSync.status', 'running')->where('booksSync.waiting', 2));

        app(BooksSyncService::class)->run($this->business->id, $runId);

        $this->assertSame(2, $this->saleJournals());
        $this->assertSame(0, DB::table('pending_postings')->count());
        $run = DB::table('books_syncs')->find($runId);
        $this->assertSame('done', $run->status);
        $this->assertSame(2, (int) $run->pending_posted);
        // Same dates as the sales themselves, numbers in sale order.
        $this->assertSame([$first, $second], JournalEntry::where('source_type', 'pos_invoice')->orderBy('id')->pluck('number')->all());
        $this->assertBalanced();
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('booksSync', null));
    }

    public function test_running_again_never_posts_twice(): void
    {
        $this->sell();
        $lines = JournalLine::count();
        app(BooksSyncService::class)->run($this->business->id);
        app(BooksSyncService::class)->run($this->business->id);
        $this->assertSame($lines, JournalLine::count());
        $this->assertBalanced();
    }

    public function test_the_sweep_finishes_what_a_dead_run_left(): void
    {
        $this->busy(minutesAgo: 10);
        $this->sell();
        $this->assertSame(1, DB::table('pending_postings')->count());

        // Still alive: the sweep leaves it alone.
        $this->artisan('accounting:drain-pending')->assertSuccessful();
        $this->assertSame(1, DB::table('pending_postings')->count());

        // Stuck for more than two hours: failed, and the waiting posting is posted.
        DB::table('books_syncs')->where('reason', 'manual')->update(['updated_at' => now()->subHours(3)]);
        $this->artisan('accounting:drain-pending')->expectsOutput('Posted 1 waiting journal(s).')->assertSuccessful();
        $this->assertSame('failed', DB::table('books_syncs')->where('reason', 'manual')->value('status'));
        $this->assertSame(1, $this->saleJournals());
        $this->assertBalanced();
    }

    public function test_the_manual_repost_button_queues_a_run(): void
    {
        $this->post(route('accounting.backfill'))->assertSessionHas('success');
        $this->assertSame('done', DB::table('books_syncs')->where('reason', 'manual')->value('status'));
    }
}
