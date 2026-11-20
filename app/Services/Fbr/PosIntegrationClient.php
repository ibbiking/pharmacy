<?php

namespace App\Services\Fbr;

use App\Models\Fbr\FbrSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * FBR POS Integration (retail). ⚠️ UNVERIFIED: the URL, field names and response shape come from config/fbr.php,
 * which is based on public / third-party material. Check them against the official FBR / PRAL specification and the
 * business's IRIS sandbox before anyone goes live.
 */
class PosIntegrationClient implements FbrClient
{
    public function name(): string
    {
        return 'FBR POS Integration';
    }

    public function send(FbrSetting $setting, array $payload): FbrResult
    {
        $url = $setting->environment === 'production' ? config('fbr.pos.production_url') : config('fbr.pos.sandbox_url');
        if (blank($url)) {
            throw new FbrUnavailable('The FBR '.$setting->environment.' address is not configured yet (FBR_POS_'.strtoupper($setting->environment).'_URL).');
        }
        if (! $setting->hasToken()) {
            throw new FbrRejected('The FBR token is missing. Enter it in Settings → FBR Integration.');
        }

        try {
            $response = Http::withToken((string) $setting->token)->acceptJson()->timeout((int) config('fbr.timeout', 15))->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new FbrUnavailable('FBR could not be reached: '.$e->getMessage());
        }

        $body = is_array($response->json()) ? $response->json() : ['raw' => mb_substr($response->body(), 0, 1000)];
        if ($response->serverError()) {
            throw new FbrUnavailable('FBR answered with an error on its side (HTTP '.$response->status().').', $response->status(), $body);
        }
        if ($response->clientError()) {
            throw new FbrRejected('FBR refused the data (HTTP '.$response->status().'): '.$this->message($body), $response->status(), $body);
        }

        $code = (string) data_get($body, config('fbr.pos.response_code'), '');
        $number = (string) data_get($body, config('fbr.pos.response_number'), '');
        if ($code !== (string) config('fbr.pos.response_success_code') || $number === '') {
            throw new FbrRejected('FBR did not issue a number: '.$this->message($body), $response->status(), $body);
        }

        return new FbrResult($number, $response->status(), $body);
    }

    private function message(array $body): string
    {
        return mb_substr((string) (data_get($body, config('fbr.pos.response_message')) ?? data_get($body, 'message') ?? json_encode($body)), 0, 300);
    }
}
