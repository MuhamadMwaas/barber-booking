<?php

namespace Tests\Unit\Services\Fiskaly;

use App\Services\Fiskaly\FiskalyClient;
use App\Exceptions\FiskalyException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class FiskalyClientTest extends TestCase
{
    protected FiskalyClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test configuration
        config([
            'fiskaly.api_key' => 'test_api_key',
            'fiskaly.api_secret' => 'test_api_secret',
            'fiskaly.base_url' => 'https://test.fiskaly.com/api/v2',
            'fiskaly.cache.token_key' => 'test_fiskaly_token',
            'fiskaly.cache.token_ttl' => 3600,
            'fiskaly.logging.enabled' => false,
            'fiskaly.offline_mode.max_retry_attempts' => 3,
            'fiskaly.offline_mode.retry_delay' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_throws_exception_when_credentials_are_missing()
    {
        config(['fiskaly.api_key' => null]);

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('Fiskaly API credentials not configured');

        new FiskalyClient();
    }

    /** @test */
    public function it_authenticates_successfully_and_caches_token()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('test_fiskaly_token')
            ->andReturn(null);

        Http::fake([
            '*/auth' => Http::response([
                'access_token' => 'test_token_12345',
                'expires_in' => 3600,
            ], 200),
        ]);

        Cache::shouldReceive('put')
            ->once()
            ->with('test_fiskaly_token', 'test_token_12345', 3300)
            ->andReturn(true);

        $client = new FiskalyClient();
        $token = $client->authenticate();

        $this->assertEquals('test_token_12345', $token);
    }

    /** @test */
    public function it_returns_cached_token_when_available()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('test_fiskaly_token')
            ->andReturn('cached_token_12345');

        $client = new FiskalyClient();
        $token = $client->authenticate();

        $this->assertEquals('cached_token_12345', $token);
        Http::assertNothingSent();
    }

    /** @test */
    public function it_throws_exception_when_authentication_fails()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('test_fiskaly_token')
            ->andReturn(null);

        Http::fake([
            '*/auth' => Http::response([
                'error' => 'Invalid credentials',
            ], 401),
        ]);

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('Failed to obtain access token');

        $client = new FiskalyClient();
        $client->authenticate();
    }

    /** @test */
    public function it_makes_successful_get_request()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id' => Http::response([
                'tss_id' => 'test-tss-id',
                'state' => 'INITIALIZED',
            ], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->get('/tss/test-tss-id');

        $this->assertEquals('test-tss-id', $response['tss_id']);
        $this->assertEquals('INITIALIZED', $response['state']);
    }

    /** @test */
    public function it_makes_successful_post_request()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id/client/test-client-id' => Http::response([
                'client_id' => 'test-client-id',
                'serial_number' => 'TEST-001',
            ], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->post('/tss/test-tss-id/client/test-client-id', [
            'serial_number' => 'TEST-001',
        ]);

        $this->assertEquals('test-client-id', $response['client_id']);
    }

    /** @test */
    public function it_retries_on_401_and_reauthenticates()
    {
        Cache::shouldReceive('get')
            ->times(2)
            ->with('test_fiskaly_token')
            ->andReturn('expired_token', null);

        Cache::shouldReceive('forget')
            ->once()
            ->with('test_fiskaly_token');

        Cache::shouldReceive('put')
            ->once()
            ->andReturn(true);

        Http::fake([
            '*/tss/test-tss-id' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['tss_id' => 'test-tss-id'], 200),
            '*/auth' => Http::response([
                'access_token' => 'new_token',
            ], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->get('/tss/test-tss-id');

        $this->assertEquals('test-tss-id', $response['tss_id']);
    }

    /** @test */
    public function it_retries_on_server_error()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id' => Http::sequence()
                ->push(['error' => 'Server error'], 500)
                ->push(['error' => 'Server error'], 500)
                ->push(['tss_id' => 'test-tss-id'], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->get('/tss/test-tss-id');

        $this->assertEquals('test-tss-id', $response['tss_id']);
    }

    /** @test */
    public function it_throws_exception_after_max_retries()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id' => Http::response(['error' => 'Server error'], 500),
        ]);

        $this->expectException(FiskalyException::class);

        $client = new FiskalyClient();
        $client->get('/tss/test-tss-id');
    }

    /** @test */
    public function it_handles_connection_exception_with_retry()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
        });

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('Unable to connect to Fiskaly');

        $client = new FiskalyClient();
        $client->get('/tss/test-tss-id');
    }

    /** @test */
    public function it_checks_service_availability()
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $client = new FiskalyClient();
        $isAvailable = $client->isAvailable();

        $this->assertTrue($isAvailable);
    }

    /** @test */
    public function it_returns_false_when_service_is_unavailable()
    {
        Http::fake([
            '*/health' => Http::response(['status' => 'error'], 500),
        ]);

        $client = new FiskalyClient();
        $isAvailable = $client->isAvailable();

        $this->assertFalse($isAvailable);
    }

    /** @test */
    public function it_clears_cached_token()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('test_fiskaly_token');

        $client = new FiskalyClient();
        $client->clearToken();

        $this->assertNull($client->getToken());
    }

    /** @test */
    public function it_throws_exception_for_unsupported_http_method()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: INVALID');

        $client = new FiskalyClient();
        $client->request('INVALID', '/test');
    }

    /** @test */
    public function it_makes_put_request_successfully()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id' => Http::response([
                'tss_id' => 'test-tss-id',
                'state' => 'INITIALIZED',
            ], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->put('/tss/test-tss-id', ['state' => 'INITIALIZED']);

        $this->assertEquals('test-tss-id', $response['tss_id']);
    }

    /** @test */
    public function it_makes_patch_request_successfully()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id' => Http::response([
                'tss_id' => 'test-tss-id',
                'state' => 'DISABLED',
            ], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->patch('/tss/test-tss-id', ['state' => 'DISABLED']);

        $this->assertEquals('DISABLED', $response['state']);
    }

    /** @test */
    public function it_makes_delete_request_successfully()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/test-tss-id/client/test-client-id' => Http::response([], 204),
        ]);

        $client = new FiskalyClient();
        $response = $client->delete('/tss/test-tss-id/client/test-client-id');

        $this->assertIsArray($response);
    }

    /** @test */
    public function it_handles_api_error_response()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/tss/invalid-id' => Http::response([
                'error' => 'TSS not found',
                'message' => 'The requested TSS does not exist',
            ], 404),
        ]);

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('The requested TSS does not exist');

        $client = new FiskalyClient();
        $client->get('/tss/invalid-id');
    }

    /** @test */
    public function it_authenticates_before_making_request_if_no_token()
    {
        Cache::shouldReceive('get')
            ->times(2)
            ->with('test_fiskaly_token')
            ->andReturn(null, null);

        Cache::shouldReceive('put')->once()->andReturn(true);

        Http::fake([
            '*/auth' => Http::response(['access_token' => 'new_token'], 200),
            '*/tss/test-tss-id' => Http::response(['tss_id' => 'test-tss-id'], 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->get('/tss/test-tss-id');

        $this->assertEquals('test-tss-id', $response['tss_id']);
    }

    /** @test */
    public function it_returns_empty_array_when_response_has_no_json()
    {
        Cache::shouldReceive('get')->andReturn('test_token');

        Http::fake([
            '*/test' => Http::response('', 200),
        ]);

        $client = new FiskalyClient();
        $response = $client->get('/test');

        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }
}
