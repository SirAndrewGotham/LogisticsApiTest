<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
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
