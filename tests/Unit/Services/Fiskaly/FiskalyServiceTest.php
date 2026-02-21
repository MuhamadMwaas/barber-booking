<?php

namespace Tests\Unit\Services\Fiskaly;

use App\Services\Fiskaly\FiskalyService;
use App\Services\Fiskaly\FiskalyClient;
use App\Services\Fiskaly\TssService;
use App\Services\Fiskaly\ClientService;
use App\Services\Fiskaly\TransactionService;
use App\Services\Fiskaly\ReceiptService;
use App\Exceptions\FiskalyException;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class FiskalyServiceTest extends TestCase
{
    protected FiskalyService $fiskalyService;
    protected $mockClient;
    protected $mockTssService;
    protected $mockClientService;
    protected $mockTransactionService;
    protected $mockReceiptService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(FiskalyClient::class);
        $this->mockTssService = Mockery::mock(TssService::class);
        $this->mockClientService = Mockery::mock(ClientService::class);
        $this->mockTransactionService = Mockery::mock(TransactionService::class);
        $this->mockReceiptService = Mockery::mock(ReceiptService::class);

        $this->fiskalyService = new FiskalyService(
            $this->mockClient,
            $this->mockTssService,
            $this->mockClientService,
            $this->mockTransactionService,
            $this->mockReceiptService
        );

        config([
            'fiskaly.tss.id' => 'test-tss-id',
            'fiskaly.tss.description' => 'Test TSS',
            'fiskaly.client.id' => 'test-client-id',
            'fiskaly.client.serial_number' => 'TEST-001',
            'fiskaly.offline_mode.enabled' => true,
            'fiskaly.api_key' => 'test-key',
            'fiskaly.api_secret' => 'test-secret',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_initializes_fiskaly_successfully()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);

        $this->mockClient->shouldReceive('authenticate')->once()->andReturn('test-token');
        $this->mockTssService->shouldReceive('create')->once()->andReturn([
            'tss_id' => 'new-tss-id',
            'puk' => 'test-puk-12345',
            'state' => 'INITIALIZED',
        ]);
        $this->mockTssService->shouldReceive('initialize')->once()->andReturn(['state' => 'INITIALIZED']);
        $this->mockClientService->shouldReceive('createOrUpdate')->once()->andReturn([
            'client_id' => 'new-client-id',
            'serial_number' => 'TEST-001',
        ]);

        $result = $this->fiskalyService->initialize();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tss', $result);
    }

    /** @test */
    public function it_signs_invoice_successfully()
    {
        $invoice = Mockery::mock(Invoice::class);
        $invoice->id = 1;
        $invoice->shouldReceive('update')->once()->andReturn(true);

        Log::shouldReceive('info')->andReturn(true);
        $this->mockClient->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->mockTransactionService->shouldReceive('createFromInvoice')->once()->andReturn([
            'transaction_id' => 'tx-123',
            'number' => 456,
            'signature' => ['value' => 'sig-value'],
        ]);

        $result = $this->fiskalyService->signInvoice($invoice);

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_validates_configuration()
    {
        $this->mockClient->shouldReceive('authenticate')->once()->andReturn('token');
        $this->mockTssService->shouldReceive('checkState')->once()->andReturn('INITIALIZED');

        $result = $this->fiskalyService->validateConfiguration();

        $this->assertTrue($result['valid']);
    }
}
