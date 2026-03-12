<?php

namespace App\Http\Controllers\Api;

use App\Services\SlotService;
use Illuminate\Http\JsonResponse;

class HoldController
{
    /**
     * Create a new HoldController instance.
     *
     * @param SlotService $slotService Service used to confirm and cancel slot holds.
     */
    public function __construct(
        private readonly SlotService $slotService
    ) {}

    /**
     * Confirms the specified hold and returns a confirmation message.
     *
     * @param int|string $holdId The identifier of the hold to confirm.
     * @return \Illuminate\Http\JsonResponse JSON with key "message" set to "Hold confirmed".
     */
    public function confirm($holdId): JsonResponse
    {
        $this->slotService->confirmHold($holdId);

        return response()->json(['message' => 'Hold confirmed']);
    }

    /**
     * Cancel a hold by its identifier.
     *
     * @param mixed $holdId The identifier of the hold to cancel.
     * @return \Illuminate\Http\JsonResponse JSON with `['message' => 'Hold cancelled']`.
     */
    public function destroy($holdId): JsonResponse
    {
        $this->slotService->cancelHold($holdId);

        return response()->json(['message' => 'Hold cancelled']);
    }
}
