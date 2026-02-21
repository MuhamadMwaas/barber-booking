<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\FiskalyException;
use Tests\TestCase;

class FiskalyExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_exception_with_message_and_status_code()
    {
        $exception = new FiskalyException('Test error', 404);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(404, $exception->getStatusCode());
    }

    /** @test */
    public function it_defaults_to_500_status_code()
    {
        $exception = new FiskalyException('Test error');

        $this->assertEquals(500, $exception->getStatusCode());
    }

    /** @test */
    public function it_renders_json_response()
    {
        $exception = new FiskalyException('Test error', 400);
        $request = request();

        $response = $exception->render($request);

        $this->assertEquals(400, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Fiskaly Error', $data['error']);
        $this->assertEquals('Test error', $data['message']);
    }

    /** @test */
    public function it_uses_500_for_invalid_status_codes()
    {
        $exception = new FiskalyException('Test error', 999);
        $request = request();

        $response = $exception->render($request);

        $this->assertEquals(500, $response->getStatusCode());
    }
}
