<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SlotCapacityException extends HttpException
{
    /**
     * Initialize a SlotCapacityException representing an HTTP 409 Conflict when a slot has no capacity.
     *
     * @param string $message Human-readable error message; defaults to 'No capacity available'.
     * @param \Throwable|null $previous Optional previous exception for chaining.
     * @param array $headers Optional HTTP headers to include in the response.
     */
    public function __construct(string $message = 'No capacity available', \Throwable $previous = null, array $headers = [])
    {
        parent::__construct(409, $message, $previous, $headers);
    }
}
