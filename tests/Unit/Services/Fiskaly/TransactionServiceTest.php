<?php

namespace Tests\Unit\Services\Fiskaly;

use App\Services\Fiskaly\TransactionService;
use App\Services\Fiskaly\FiskalyClient;
use App\Exceptions\FiskalyException;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class TransactionServiceTest extends TestCase
{
    protected TransactionService $transactionService;
    protected $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(FiskalyClient::class);
        $this->transactionService = new TransactionService($this->mockClient);

        config([
            'fiskaly.tss.id' => 'test-tss-id',
            'fiskaly.client.id' => 'test-client-id',
            'fiskaly.tax.rates.standard' => 19.00,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_starts_transaction_successfully()
    {
        $expectedResponse = [
            'number' => 123,
            'time_start' => '2024-01-15T10:00:00Z',
            'state' => 'ACTIVE',
        ];

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->with(
                Mockery::pattern('/\/tss\/test-tss-id\/tx\/.+/'),
                ['state' => 'ACTIVE', 'client_id' => 'test-client-id']
            )
            ->andReturn($expectedResponse);

        $result = $this->transactionService->start('test-tss-id', 'test-client-id');

        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertEquals('test-tss-id', $result['tss_id']);
        $this->assertEquals('test-client-id', $result['client_id']);
        $this->assertEquals(123, $result['number']);
        $this->assertEquals('ACTIVE', $result['state']);
    }

    /** @test */
    public function it_throws_exception_when_start_fails()
    {
        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('Failed to start transaction');

        $this->transactionService->start('test-tss-id', 'test-client-id');
    }

    /** @test */
    public function it_updates_transaction_successfully()
    {
        $expectedResponse = [
            'state' => 'ACTIVE',
        ];

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->andReturn($expectedResponse);

        $result = $this->transactionService->update('test-tss-id', 'tx-123', [
            'items' => [
                ['name' => 'Service', 'amount' => 100.00, 'vat_rate' => 19.00],
            ],
        ]);

        $this->assertEquals('tx-123', $result['transaction_id']);
        $this->assertEquals('ACTIVE', $result['state']);
    }

    /** @test */
    public function it_finishes_transaction_successfully()
    {
        $expectedResponse = [
            'number' => 123,
            'time_start' => '2024-01-15T10:00:00Z',
            'time_end' => '2024-01-15T10:05:00Z',
            'signature' => [
                'value' => 'signature-value',
                'algorithm' => 'ecdsa-plain-SHA256',
                'counter' => 456,
            ],
            'qr_code_data' => 'qr-code-data',
            'tss_serial_number' => 'TSS-001',
            'client_serial_number' => 'CLIENT-001',
        ];

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->andReturn($expectedResponse);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [
                ['name' => 'Service', 'amount' => 100.00, 'vat_rate' => 19.00],
            ],
            'payments' => [
                ['type' => 'CASH', 'amount' => 100.00],
            ],
        ]);

        $this->assertEquals('tx-123', $result['transaction_id']);
        $this->assertEquals('FINISHED', $result['state']);
        $this->assertEquals(123, $result['number']);
        $this->assertArrayHasKey('signature', $result);
        $this->assertEquals('signature-value', $result['signature']['value']);
    }

    /** @test */
    public function it_cancels_transaction_successfully()
    {
        $expectedResponse = [];

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->with('/tss/test-tss-id/tx/tx-123', ['state' => 'CANCELLED'])
            ->andReturn($expectedResponse);

        $result = $this->transactionService->cancel('test-tss-id', 'tx-123');

        $this->assertEquals('tx-123', $result['transaction_id']);
        $this->assertEquals('CANCELLED', $result['state']);
    }

    /** @test */
    public function it_gets_transaction_details()
    {
        $expectedResponse = [
            'transaction_id' => 'tx-123',
            'state' => 'FINISHED',
            'number' => 123,
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id/tx/tx-123')
            ->andReturn($expectedResponse);

        $result = $this->transactionService->get('test-tss-id', 'tx-123');

        $this->assertEquals('tx-123', $result['transaction_id']);
        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_lists_transactions()
    {
        $expectedResponse = [
            'data' => [
                ['transaction_id' => 'tx-1', 'state' => 'FINISHED'],
                ['transaction_id' => 'tx-2', 'state' => 'ACTIVE'],
            ],
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id/tx', [])
            ->andReturn($expectedResponse);

        $result = $this->transactionService->list('test-tss-id');

        $this->assertCount(2, $result);
        $this->assertEquals('tx-1', $result[0]['transaction_id']);
    }

    /** @test */
    public function it_builds_vat_rates_correctly()
    {
        $items = [
            ['amount' => 100.00, 'vat_rate' => 19.00],
            ['amount' => 50.00, 'vat_rate' => 19.00],
            ['amount' => 30.00, 'vat_rate' => 7.00],
        ];

        $this->mockClient->shouldReceive('put')->once()->andReturn([
            'state' => 'FINISHED',
            'signature' => [],
        ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => $items,
            'payments' => [['type' => 'CASH', 'amount' => 180.00]],
        ]);

        $this->assertArrayHasKey('transaction_id', $result);
    }

    /** @test */
    public function it_maps_payment_types_correctly()
    {
        $payments = [
            ['type' => 'CASH', 'amount' => 50.00],
            ['type' => 'CARD', 'amount' => 30.00],
            ['type' => 'CREDIT_CARD', 'amount' => 20.00],
            ['type' => 'STRIPE', 'amount' => 10.00],
        ];

        $this->mockClient->shouldReceive('put')->once()->andReturn([
            'state' => 'FINISHED',
            'signature' => [],
        ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [['amount' => 110.00, 'vat_rate' => 19.00]],
            'payments' => $payments,
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_processes_complete_transaction()
    {
        $this->mockClient
            ->shouldReceive('put')
            ->twice()
            ->andReturn(
                ['state' => 'ACTIVE', 'number' => 123],
                ['state' => 'FINISHED', 'signature' => []]
            );

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->process('test-tss-id', 'test-client-id', [
            'items' => [['amount' => 100.00, 'vat_rate' => 19.00]],
            'payments' => [['type' => 'CASH', 'amount' => 100.00]],
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_handles_database_error_when_storing_transaction()
    {
        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->andReturn([
                'state' => 'FINISHED',
                'signature' => [],
            ]);

        DB::shouldReceive('table')
            ->andThrow(new \Exception('Database error'));

        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->once()->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [['amount' => 100.00]],
            'payments' => [['type' => 'CASH', 'amount' => 100.00]],
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_uses_default_vat_rate_when_not_provided()
    {
        $this->mockClient->shouldReceive('put')->once()->andReturn([
            'state' => 'FINISHED',
            'signature' => [],
        ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [['amount' => 100.00]], // No vat_rate
            'payments' => [['type' => 'CASH', 'amount' => 100.00]],
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_maps_unknown_payment_type_to_cash()
    {
        $this->mockClient->shouldReceive('put')->once()->andReturn([
            'state' => 'FINISHED',
            'signature' => [],
        ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [['amount' => 100.00]],
            'payments' => [['type' => 'UNKNOWN_TYPE', 'amount' => 100.00]],
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_handles_empty_items_array()
    {
        $this->mockClient->shouldReceive('put')->once()->andReturn([
            'state' => 'FINISHED',
            'signature' => [],
        ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [],
            'payments' => [['type' => 'CASH', 'amount' => 0.00]],
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }

    /** @test */
    public function it_handles_empty_payments_array()
    {
        $this->mockClient->shouldReceive('put')->once()->andReturn([
            'state' => 'FINISHED',
            'signature' => [],
        ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->transactionService->finish('test-tss-id', 'tx-123', [
            'items' => [['amount' => 100.00]],
            'payments' => [],
        ]);

        $this->assertEquals('FINISHED', $result['state']);
    }
}
