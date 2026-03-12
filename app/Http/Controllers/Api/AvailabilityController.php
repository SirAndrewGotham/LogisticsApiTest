<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SlotCollection;
use App\Models\Slot;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AvailabilityController
{
    /**
     * Get available slots
     */
    public function __invoke(): SlotCollection
    {
        $slots = Slot::query()
            ->select(['id', 'capacity', 'remaining'])
            ->orderBy('id')
            ->get();

        return new SlotCollection($slots);
    }
}
