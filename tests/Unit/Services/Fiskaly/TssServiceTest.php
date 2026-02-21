<?php

namespace Tests\Unit\Services\Fiskaly;

use App\Services\Fiskaly\TssService;
use App\Services\Fiskaly\FiskalyClient;
use App\Exceptions\FiskalyException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class TssServiceTest extends TestCase
{
    protected TssService $tssService;
    protected $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(FiskalyClient::class);
        $this->tssService = new TssService($this->mockClient);

        config([
            'fiskaly.tss.description' => 'Test TSS',
            'fiskaly.tss.id' => 'test-tss-id',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_tss_successfully()
    {
        $expectedResponse = [
            'puk' => 'test-puk-12345',
            'serial_number' => 'TSS-001',
            'certificate' => 'test-certificate',
            'state' => 'INITIALIZED',
            'metadata' => [
                'created_at' => '2024-01-15T00:00:00Z',
            ],
        ];

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->with(Mockery::pattern('/\/tss\/.+/'), Mockery::type('array'))
            ->andReturn($expectedResponse);

        DB::shouldReceive('table')
            ->with('fiskaly_tss')
            ->andReturnSelf();

        DB::shouldReceive('updateOrInsert')
            ->once()
            ->andReturn(true);

        Log::shouldReceive('info')->andReturn(true);

        $result = $this->tssService->create([
            'description' => 'Test TSS',
        ]);

        $this->assertArrayHasKey('tss_id', $result);
        $this->assertEquals('test-puk-12345', $result['puk']);
        $this->assertEquals('TSS-001', $result['serial_number']);
        $this->assertEquals('INITIALIZED', $result['state']);
    }

    /** @test */
    public function it_throws_exception_when_tss_creation_fails()
    {
        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('Failed to create TSS');

        $this->tssService->create();
    }

    /** @test */
    public function it_gets_tss_details_successfully()
    {
        $expectedResponse = [
            'tss_id' => 'test-tss-id',
            'state' => 'INITIALIZED',
            'serial_number' => 'TSS-001',
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id')
            ->andReturn($expectedResponse);

        $result = $this->tssService->get('test-tss-id');

        $this->assertEquals('test-tss-id', $result['tss_id']);
        $this->assertEquals('INITIALIZED', $result['state']);
    }

    /** @test */
    public function it_lists_all_tss_instances()
    {
        $expectedResponse = [
            'data' => [
                ['tss_id' => 'tss-1', 'state' => 'INITIALIZED'],
                ['tss_id' => 'tss-2', 'state' => 'INITIALIZED'],
            ],
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss')
            ->andReturn($expectedResponse);

        $result = $this->tssService->list();

        $this->assertCount(2, $result);
        $this->assertEquals('tss-1', $result[0]['tss_id']);
    }

    /** @test */
    public function it_initializes_tss_successfully()
    {
        $expectedResponse = [
            'tss_id' => 'test-tss-id',
            'state' => 'INITIALIZED',
        ];

        $this->mockClient
            ->shouldReceive('patch')
            ->once()
            ->with('/tss/test-tss-id', ['state' => 'INITIALIZED'])
            ->andReturn($expectedResponse);

        $result = $this->tssService->initialize('test-tss-id');

        $this->assertEquals('INITIALIZED', $result['state']);
    }

    /** @test */
    public function it_disables_tss_successfully()
    {
        $expectedResponse = [
            'tss_id' => 'test-tss-id',
            'state' => 'DISABLED',
        ];

        $this->mockClient
            ->shouldReceive('patch')
            ->once()
            ->with('/tss/test-tss-id', ['state' => 'DISABLED'])
            ->andReturn($expectedResponse);

        $result = $this->tssService->disable('test-tss-id');

        $this->assertEquals('DISABLED', $result['state']);
    }

    /** @test */
    public function it_authenticates_admin_successfully()
    {
        $expectedResponse = [
            'admin_token' => 'admin-token-12345',
        ];

        $this->mockClient
            ->shouldReceive('post')
            ->once()
            ->with('/tss/test-tss-id/admin/auth', ['admin_pin' => '12345'])
            ->andReturn($expectedResponse);

        $result = $this->tssService->authenticateAdmin('test-tss-id', '12345');

        $this->assertArrayHasKey('admin_token', $result);
    }

    /** @test */
    public function it_changes_admin_pin_successfully()
    {
        $expectedResponse = [
            'success' => true,
        ];

        $this->mockClient
            ->shouldReceive('patch')
            ->once()
            ->with('/tss/test-tss-id/admin', [
                'admin_pin' => 'old-pin',
                'new_admin_pin' => 'new-pin',
            ])
            ->andReturn($expectedResponse);

        $result = $this->tssService->changeAdminPin('test-tss-id', 'old-pin', 'new-pin');

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_unblocks_tss_with_puk()
    {
        $expectedResponse = [
            'success' => true,
            'state' => 'INITIALIZED',
        ];

        $this->mockClient
            ->shouldReceive('patch')
            ->once()
            ->with('/tss/test-tss-id/admin', [
                'puk' => 'test-puk',
                'new_admin_pin' => 'new-pin',
            ])
            ->andReturn($expectedResponse);

        $result = $this->tssService->unblockWithPuk('test-tss-id', 'test-puk', 'new-pin');

        $this->assertTrue($result['success']);
    }

    /** @test */
    public function it_exports_tss_data()
    {
        $expectedResponse = [
            'export_id' => 'export-12345',
            'download_url' => 'https://example.com/export.tar',
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id/export', Mockery::type('array'))
            ->andReturn($expectedResponse);

        $result = $this->tssService->export('test-tss-id', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
        ]);

        $this->assertArrayHasKey('export_id', $result);
    }

    /** @test */
    public function it_gets_tss_certificate()
    {
        $expectedResponse = [
            'certificate' => 'test-certificate-content',
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id')
            ->andReturn($expectedResponse);

        $certificate = $this->tssService->getCertificate('test-tss-id');

        $this->assertEquals('test-certificate-content', $certificate);
    }

    /** @test */
    public function it_checks_tss_state()
    {
        $expectedResponse = [
            'state' => 'INITIALIZED',
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id')
            ->andReturn($expectedResponse);

        $state = $this->tssService->checkState('test-tss-id');

        $this->assertEquals('INITIALIZED', $state);
    }

    /** @test */
    public function it_returns_unknown_state_on_error()
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $state = $this->tssService->checkState('test-tss-id');

        $this->assertEquals('UNKNOWN', $state);
    }

    /** @test */
    public function it_validates_configuration_successfully()
    {
        $expectedResponse = [
            'state' => 'INITIALIZED',
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->with('/tss/test-tss-id')
            ->andReturn($expectedResponse);

        $isValid = $this->tssService->validateConfiguration();

        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_throws_exception_when_tss_id_not_configured()
    {
        config(['fiskaly.tss.id' => null]);

        $this->expectException(FiskalyException::class);
        $this->expectExceptionMessage('TSS ID not configured');

        $this->tssService->validateConfiguration();
    }

    /** @test */
    public function it_returns_false_when_tss_state_is_not_initialized()
    {
        $expectedResponse = [
            'state' => 'DISABLED',
        ];

        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->andReturn($expectedResponse);

        $isValid = $this->tssService->validateConfiguration();

        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_handles_database_error_gracefully_when_storing_tss_data()
    {
        $expectedResponse = [
            'puk' => 'test-puk',
            'serial_number' => 'TSS-001',
            'state' => 'INITIALIZED',
        ];

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->andReturn($expectedResponse);

        DB::shouldReceive('table')
            ->andThrow(new \Exception('Database error'));

        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->once()->andReturn(true);

        // Should not throw exception, just log error
        $result = $this->tssService->create();

        $this->assertArrayHasKey('tss_id', $result);
    }

    /** @test */
    public function it_creates_tss_with_custom_tss_id()
    {
        $customTssId = 'custom-tss-id-12345';

        $this->mockClient
            ->shouldReceive('put')
            ->once()
            ->with("/tss/{$customTssId}", Mockery::type('array'))
            ->andReturn([
                'puk' => 'test-puk',
                'state' => 'INITIALIZED',
            ]);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $result = $this->tssService->create([
            'tss_id' => $customTssId,
        ]);

        $this->assertEquals($customTssId, $result['tss_id']);
    }

    /** @test */
    public function it_returns_empty_array_when_list_has_no_data()
    {
        $this->mockClient
            ->shouldReceive('get')
            ->once()
            ->andReturn([]);

        $result = $this->tssService->list();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
