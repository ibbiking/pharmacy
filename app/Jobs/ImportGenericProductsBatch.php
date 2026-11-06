<?php

namespace App\Jobs;

use App\Services\Catalogue\GenericImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Imports one batch of generic catalogue entries right after it is queued (Roadmap 5.6a). Each item is isolated inside
 * GenericImportService::processBatch; if the worker itself dies, the 15-minute sweep releases and finishes the batch.
 */
class ImportGenericProductsBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public int $timeout = 900;

    public function __construct(public int $batchId)
    {
    }

    public function handle(GenericImportService $imports): void
    {
        $imports->processBatch($this->batchId);
    }
}
