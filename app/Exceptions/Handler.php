<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register application exception handling callbacks.
     *
     * Registers a renderable callback for SlotCapacityException that returns a JSON
     * response containing an `error` message from the exception and uses the
     * exception's status code as the HTTP response status.
     */
    public function register(): void
    {
        $this->renderable(function (SlotCapacityException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], $e->getStatusCode());
        });
    }
}
