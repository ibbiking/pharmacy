<?php

namespace Tests\Unit;

use App\Models\Fbr\FbrSetting;
use App\Services\Fbr\FakeFbrClient;
use App\Services\Fbr\FbrRejected;
use App\Services\Fbr\FbrUnavailable;
use App\Services\Fbr\PosIntegrationClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FBR groundwork (docs/FBR_INTEGRATION.md, step 6, parked 2026-11-20): the clients are not wired into the app yet.
 * These tests keep them working until the feature resumes. No database: settings are unsaved models.
 */
class FbrClientTest extends TestCase
{
    private const SANDBOX = 'https://fbr.test/sandbox/PostData';

    protected function setUp(): void
    {
        parent::setUp();
        config(['fbr.pos.sandbox_url' => self::SANDBOX, 'fbr.pos.production_url' => null]);
        FakeFbrClient::reset();
    }

    private function setting(array $attributes = []): FbrSetting
    {
        return new FbrSetting($attributes + ['environment' => 'sandbox', 'ntn' => '1234567', 'token' => 'secret-token']);
    }

    public function test_a_successful_answer_returns_the_fbr_number_and_sends_the_token(): void
    {
        Http::fake([self::SANDBOX => Http::response(['Code' => '100', 'InvoiceNumber' => 'FBR-0001', 'Response' => 'OK'])]);

        $result = (new PosIntegrationClient)->send($this->setting(), ['USIN' => 'INV-1']);

        $this->assertSame('FBR-0001', $result->number);
        Http::assertSent(fn ($request) => $request->url() === self::SANDBOX && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request['USIN'] === 'INV-1');
    }

    public function test_a_refusal_is_not_retryable(): void
    {
        Http::fake([self::SANDBOX => Http::response(['Code' => '401', 'Response' => 'Invalid POSID'], 400)]);

        $this->expectException(FbrRejected::class);
        $this->expectExceptionMessage('Invalid POSID');
        (new PosIntegrationClient)->send($this->setting(), []);
    }

    public function test_a_success_status_without_a_number_is_a_refusal(): void
    {
        Http::fake([self::SANDBOX => Http::response(['Code' => '102', 'Response' => 'Duplicate USIN'])]);

        $this->expectException(FbrRejected::class);
        (new PosIntegrationClient)->send($this->setting(), []);
    }

    public function test_a_server_error_is_retryable(): void
    {
        Http::fake([self::SANDBOX => Http::response('down', 503)]);

        $this->expectException(FbrUnavailable::class);
        (new PosIntegrationClient)->send($this->setting(), []);
    }

    public function test_nothing_is_sent_without_an_address_or_a_token(): void
    {
        Http::fake();

        try {
            (new PosIntegrationClient)->send($this->setting(['environment' => 'production']), []);
            $this->fail('production has no address configured');
        } catch (FbrUnavailable $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }
        try {
            (new PosIntegrationClient)->send($this->setting(['token' => null]), []);
            $this->fail('a token is required');
        } catch (FbrRejected $e) {
            $this->assertStringContainsString('token is missing', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_the_token_is_never_serialised(): void
    {
        $this->assertArrayNotHasKey('token', $this->setting()->toArray());
        $this->assertTrue($this->setting()->hasToken());
    }

    public function test_the_fake_client_follows_the_scripted_outcomes(): void
    {
        FakeFbrClient::$next = ['ok', 'reject', 'down'];
        $fake = new FakeFbrClient;

        $this->assertStringStartsWith('FAKE-', $fake->send($this->setting(), ['n' => 1])->number);
        try {
            $fake->send($this->setting(), ['n' => 2]);
            $this->fail('second call is a rejection');
        } catch (FbrRejected) {
        }
        try {
            $fake->send($this->setting(), ['n' => 3]);
            $this->fail('third call is FBR down');
        } catch (FbrUnavailable) {
        }
        $this->assertCount(3, FakeFbrClient::$sent);
    }
}
