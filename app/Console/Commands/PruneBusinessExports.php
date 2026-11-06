<?php

namespace App\Console\Commands;

use App\Models\BusinessExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Roadmap 13d: delete export files older than their 7-day life (the row stays, marked expired by its date). */
class PruneBusinessExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired per-business data export files';

    public function handle(): int
    {
        $n = 0;
        BusinessExport::withoutGlobalScopes()->whereNotNull('file_path')->where('expires_at', '<', now())->each(function (BusinessExport $e) use (&$n) {
            Storage::disk('local')->delete($e->file_path);
            $e->update(['file_path' => null]);
            $n++;
        });
        // Stuck jobs (worker died) are marked failed after 2 hours.
        BusinessExport::withoutGlobalScopes()->whereIn('status', ['pending', 'running'])->where('created_at', '<', now()->subHours(2))->update(['status' => 'failed']);
        $this->info("Deleted {$n} expired export(s).");

        return self::SUCCESS;
    }
}
