<?php

namespace App\Exceptions;

use Exception;

class SlotCapacityException extends Exception
{
    private ?int $slotId;

    public function __construct(string $message = 'No capacity available in this slot', int $slotId = null)
    {
        parent::__construct($message);
        $this->slotId = $slotId;
    }

    public function getSlotId(): ?int
    {
        return $this->slotId;
    }
}
