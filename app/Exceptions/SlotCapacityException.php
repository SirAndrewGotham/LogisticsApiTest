<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class SlotCapacityException extends HttpException
{
    public function __construct(string $message = 'No capacity available', \Throwable $previous = null, array $headers = [])
    {
        parent::__construct(409, $message, $previous, $headers);
    }
}
