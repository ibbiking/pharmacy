<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Admin\GenericProductController;

class ProcessGenericProductBulkImports extends Command
{
    protected $signature = 'generic-products:process-bulk-imports';
    protected $description = 'Process pending bulk generic product import batches';

    public function handle()
    {
        $summary = GenericProductController::processPendingBulkImportBatches();
        $this->info("Processed {$summary['processed']} batch | Imported: {$summary['imported']} | Skipped: {$summary['skipped']}");
        return Command::SUCCESS;
    }
}
