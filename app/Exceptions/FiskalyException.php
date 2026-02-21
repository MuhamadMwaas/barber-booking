<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class FiskalyException extends Exception
{
    protected int $statusCode;

    public function __construct(string $message = "", int $statusCode = 500, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Render the exception for HTTP response
     */
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'error' => 'Fiskaly Error',
            'message' => $this->getMessage(),
            'status_code' => $this->statusCode,
        ], $this->statusCode >= 400 && $this->statusCode < 600 ? $this->statusCode : 500);
    }

    /**
     * Report the exception to logging
     */
    public function report()
    {
        Log::error('[Fiskaly Exception] ' . $this->getMessage(), [
            'status_code' => $this->statusCode,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ]);
    }
}
