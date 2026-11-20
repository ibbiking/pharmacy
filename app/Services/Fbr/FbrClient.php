<?php

namespace App\Services\Fbr;

use App\Models\Fbr\FbrSetting;

/**
 * Talks to one FBR regime. POS Integration is PosIntegrationClient; Digital Invoicing (B2B) gets its own
 * implementation later with the same contract. FakeFbrClient stands in for both in tests and local demos.
 */
interface FbrClient
{
    /**
     * Send one invoice / return (already in FBR’s field names, as mapped in config/fbr.php → pos.fields) and return FBR's answer.
     *
     * @throws FbrRejected    FBR refused the data (4xx or an error code): never retried, needs fixing.
     * @throws FbrUnavailable FBR could not be reached or failed on its side (5xx, timeout): safe to retry.
     */
    public function send(FbrSetting $setting, array $payload): FbrResult;

    /** Short name for logs and the status panel. */
    public function name(): string;
}
