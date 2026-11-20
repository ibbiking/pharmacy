<?php

namespace App\Services\Fbr;

use App\Models\Fbr\FbrSetting;

/**
 * Stand-in for FBR in tests and local demos (FBR_DRIVER=fake). Issues numbers starting with "FAKE-", which receipts
 * mark as "not valid with FBR". A business can never go live with it outside the local / testing environments.
 *
 * Tests steer it with FakeFbrClient::$next: 'ok' (default), 'reject' (4xx) or 'down' (5xx / timeout), optionally a
 * queue of outcomes, one per call.
 */
class FakeFbrClient implements FbrClient
{
    /** @var string|array<int, string> */
    public static string|array $next = 'ok';

    /** @var array<int, array> every payload sent (tests read it) */
    public static array $sent = [];

    public static function reset(): void
    {
        self::$next = 'ok';
        self::$sent = [];
    }

    public function name(): string
    {
        return 'Test FBR (fake)';
    }

    public function send(FbrSetting $setting, array $payload): FbrResult
    {
        self::$sent[] = $payload;
        $outcome = is_array(self::$next) ? (array_shift(self::$next) ?? 'ok') : self::$next;

        return match ($outcome) {
            'reject' => throw new FbrRejected('FBR refused the data (HTTP 400): test rejection.', 400, ['Code' => '401', 'Response' => 'Invalid data (test)']),
            'down' => throw new FbrUnavailable('FBR could not be reached (test).', 503, ['Response' => 'Service unavailable (test)']),
            default => new FbrResult('FAKE-'.strtoupper(substr(sha1(json_encode($payload).microtime()), 0, 12)), 200,
                ['Code' => '100', 'Response' => 'Fiscal invoice number generated successfully (test).']),
        };
    }
}
