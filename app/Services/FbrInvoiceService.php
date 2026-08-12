<?php

namespace App\Services;

use App\Exceptions\FbrNotConfiguredException;
use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ============================================================================
 *  NOT PRODUCTION-READY — placeholder integration, pending real FBR API specs
 * ============================================================================
 *
 * This class exists so the rest of the app (POSController::saveInvoice) has a
 * single, clean seam to call when a business chooses "FBR Invoice" numbering
 * in Business Settings. The actual HTTP call below (endpoint path, request
 * fields, response shape) is a best-guess placeholder based on the general
 * shape of FBR's Digital Invoicing / POS integration APIs — it has NOT been
 * verified against a real FBR sandbox or production endpoint.
 *
 * Before this can go live, we need from the business owner:
 *   1. Which FBR scheme applies: the Sales Tax "POS Integration" for Tier-1
 *      retailers (SRO 1006(I)/2021, PRAL-operated), or the newer Digital
 *      Invoicing regime — these have different endpoints and payloads.
 *   2. Sandbox and/or production base URL + bearer token issued by FBR/PRAL.
 *   3. The exact request schema (their API docs/Postman collection), since
 *      posting a malformed payload to a real tax authority is not something
 *      to guess at.
 *   4. What should happen at the POS if FBR is unreachable at checkout time
 *      (currently: the sale is blocked and rolled back — see saveInvoice()).
 *
 * Until then, businesses should stay on invoice_source = 'local' (the
 * default). Selecting "FBR Invoice" without finishing the link in Business
 * Settings raises FbrNotConfiguredException before any API call is attempted.
 */
class FbrInvoiceService
{
    /**
     * Ask FBR for a real invoice number for this sale and return it.
     *
     * @param  Business $business
     * @param  array<string,mixed> $invoicePayload Sale summary (totals, items, etc.)
     * @return string The FBR-issued invoice number to print on the receipt.
     *
     * @throws FbrNotConfiguredException
     * @throws RuntimeException
     */
    public function generateInvoiceNumber(Business $business, array $invoicePayload): string
    {
        if (!$business->hasFbrCredentials()) {
            throw new FbrNotConfiguredException(
                'This business is set to FBR invoicing but has not finished linking its FBR details in Business Settings.'
            );
        }

        $baseUrl = $business->fbr_environment === 'production'
            ? config('services.fbr.production_url')
            : config('services.fbr.sandbox_url');

        if (blank($baseUrl)) {
            throw new RuntimeException('FBR API base URL is not configured (services.fbr.sandbox_url / production_url).');
        }

        // --- PLACEHOLDER REQUEST — see class docblock. Do not trust field names below. ---
        $payload = array_merge($invoicePayload, [
            'sellerNTNCNIC'     => $business->fbr_ntn,
            'sellerSTRN'        => $business->fbr_strn,
            'posRegistrationNo' => $business->fbr_pos_registration_no,
            'sellerBusinessName' => $business->fbr_business_name ?: $business->name,
        ]);

        try {
            $response = Http::withToken($business->fbr_api_token)
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($baseUrl, '/') . '/postinvoicedata', $payload);
        } catch (\Throwable $e) {
            Log::error('FBR invoice request failed to send', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Could not reach FBR: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            Log::error('FBR rejected the invoice', [
                'business_id' => $business->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('FBR rejected this invoice (HTTP ' . $response->status() . ').');
        }

        $data = $response->json() ?? [];
        $fbrInvoiceNo = $data['invoiceNumber'] ?? $data['fbrInvoiceNumber'] ?? null;

        if (blank($fbrInvoiceNo)) {
            Log::error('FBR response missing invoice number', ['business_id' => $business->id, 'body' => $response->body()]);

            throw new RuntimeException('FBR did not return an invoice number.');
        }

        return (string) $fbrInvoiceNo;
    }

    /**
     * Same placeholder status as generateInvoiceNumber() above — a sales
     * return against an FBR-numbered invoice is a credit note in FBR's model,
     * which (depending on the scheme) may need its own FBR-issued reference
     * rather than a locally generated RET- number. Endpoint/payload here are
     * unverified guesses; confirm against the business's real FBR docs.
     *
     * @param  Business $business
     * @param  array<string,mixed> $creditNotePayload Original invoice + returned items summary.
     */
    public function generateCreditNoteNumber(Business $business, array $creditNotePayload): string
    {
        if (!$business->hasFbrCredentials()) {
            throw new FbrNotConfiguredException(
                'This business is set to FBR invoicing but has not finished linking its FBR details in Business Settings.'
            );
        }

        $baseUrl = $business->fbr_environment === 'production'
            ? config('services.fbr.production_url')
            : config('services.fbr.sandbox_url');

        if (blank($baseUrl)) {
            throw new RuntimeException('FBR API base URL is not configured (services.fbr.sandbox_url / production_url).');
        }

        // --- PLACEHOLDER REQUEST — see class docblock. Do not trust field names below. ---
        $payload = array_merge($creditNotePayload, [
            'sellerNTNCNIC'     => $business->fbr_ntn,
            'sellerSTRN'        => $business->fbr_strn,
            'posRegistrationNo' => $business->fbr_pos_registration_no,
        ]);

        try {
            $response = Http::withToken($business->fbr_api_token)
                ->acceptJson()
                ->timeout(15)
                ->post(rtrim($baseUrl, '/') . '/postcreditnote', $payload);
        } catch (\Throwable $e) {
            Log::error('FBR credit note request failed to send', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Could not reach FBR: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            Log::error('FBR rejected the credit note', [
                'business_id' => $business->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('FBR rejected this return (HTTP ' . $response->status() . ').');
        }

        $data = $response->json() ?? [];
        $fbrCreditNoteNo = $data['creditNoteNumber'] ?? $data['invoiceNumber'] ?? $data['fbrInvoiceNumber'] ?? null;

        if (blank($fbrCreditNoteNo)) {
            Log::error('FBR response missing credit note number', ['business_id' => $business->id, 'body' => $response->body()]);

            throw new RuntimeException('FBR did not return a credit note number.');
        }

        return (string) $fbrCreditNoteNo;
    }
}
