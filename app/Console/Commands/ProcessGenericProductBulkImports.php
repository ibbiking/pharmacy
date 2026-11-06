<?php

namespace App\Console\Commands;

use App\Services\Catalogue\GenericImportService;
use Illuminate\Console\Command;

/** Safety sweep (every 15 minutes): bulk imports normally run at once as a queued job (Roadmap 5.6a). */
class ProcessGenericProductBulkImports extends Command
{
    protected $signature = 'generic-products:process-bulk-imports';

    protected $description = 'Release stuck generic-catalogue import batches and process any still pending';

    public function handle(GenericImportService $imports): int
    {
        $summary = $imports->sweep();
        $this->info("Released {$summary['released']} stuck batch(es); processed {$summary['processed']} pending batch(es).");

        return self::SUCCESS;
    }
}
