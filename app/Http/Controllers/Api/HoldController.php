<?php

namespace App\Http\Controllers\Api;

use App\Services\SlotService;
use Illuminate\Http\JsonResponse;

class HoldController
{
    public function __construct(
        private readonly SlotService $slotService
    ) {}

    /**
     * Confirm a hold
     * POST /api/holds/{hold}/confirm
     */
    public function confirm($holdId): JsonResponse
    {
        $this->slotService->confirmHold($holdId);

        return response()->json(['message' => 'Hold confirmed']);
    }

    /**
     * Cancel a hold
     * DELETE /api/holds/{hold}
     */
    public function destroy($holdId): JsonResponse
    {
        $this->slotService->cancelHold($holdId);

        return response()->json(['message' => 'Hold cancelled']);
    }
}
